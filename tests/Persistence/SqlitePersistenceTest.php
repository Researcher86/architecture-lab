<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Persistence;

use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Persistence\SqliteDocumentStore;
use ArchitectureLab\Persistence\SqliteOutbox;
use ArchitectureLab\Persistence\SqliteProcessedEventStore;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SqlitePersistenceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testDocumentStoreRoundTripsJson(): void
    {
        $store = new SqliteDocumentStore($this->pdo, new Counters());
        $document = [
            'title' => 'Architecture',
            'capacity' => 12,
            'nested' => ['currency' => 'EUR'],
        ];
        $store->put('courses', 'arch-101', $document);

        self::assertSame($document, $store->get('courses', 'arch-101'));
        self::assertNull($store->get('courses', 'missing'));
        self::assertSame([$document], $store->all('courses'));
    }

    public function testPutUpsertsTheSameDocument(): void
    {
        $store = new SqliteDocumentStore($this->pdo, new Counters());
        $store->put('courses', 'arch-101', ['title' => 'First']);
        $store->put('courses', 'arch-101', ['title' => 'Second']);

        self::assertSame(['title' => 'Second'], $store->get('courses', 'arch-101'));
        self::assertCount(1, $store->all('courses'));
    }

    public function testTransactionRollsBackOnFailure(): void
    {
        $store = new SqliteDocumentStore($this->pdo, new Counters());

        try {
            $store->transactional(function () use ($store): void {
                $store->put('courses', 'arch-101', ['title' => 'Uncommitted']);
                throw new RuntimeException('abort');
            });
            self::fail('The transaction should throw');
        } catch (RuntimeException) {
            self::assertNull($store->get('courses', 'arch-101'));
        }
    }

    public function testNestedTransactionUsesASavepointAndKeepsTheOuterWork(): void
    {
        $store = new SqliteDocumentStore($this->pdo, new Counters());

        $store->transactional(function () use ($store): void {
            $store->put('courses', 'outer', ['kept' => true]);

            try {
                $store->transactional(function () use ($store): void {
                    $store->put('courses', 'inner', ['rolled' => true]);
                    throw new RuntimeException('inner abort');
                });
            } catch (RuntimeException) {
                // The inner unit of work is allowed to fail without losing the outer one.
            }
        });

        self::assertSame(['kept' => true], $store->get('courses', 'outer'));
        self::assertNull($store->get('courses', 'inner'));
    }

    public function testOutboxLifecycle(): void
    {
        $outbox = new SqliteOutbox($this->pdo, new Counters());
        $outbox->record($this->event('evt-1'));
        $outbox->record($this->event('evt-2'));

        self::assertSame(2, $outbox->countPending());
        self::assertSame(['evt-1'], array_map(static fn ($message): string => $message->id, $outbox->pending(1)));

        $outbox->markDelivered('evt-1');
        self::assertSame(1, $outbox->countPending());

        $outbox->markFailed('evt-2', 'temporary', false);
        self::assertSame(1, $outbox->countPending());
        self::assertSame(1, $outbox->pending(10)[0]->attempts);

        $outbox->markFailed('evt-2', 'permanent', true);
        self::assertSame(0, $outbox->countPending());
        self::assertSame(1, $outbox->countDeadLettered());
    }

    public function testOutboxRoundTripsTheEventPayload(): void
    {
        $outbox = new SqliteOutbox($this->pdo, new Counters());
        $outbox->record($this->event('evt-1', ['student_id' => 's-1', 'price_cents' => 12000]));
        $message = $outbox->pending(1)[0];

        self::assertSame('test.event', $message->name);
        self::assertSame('agg', $message->aggregateId);
        self::assertSame(['student_id' => 's-1', 'price_cents' => 12000], $message->payload);
        self::assertSame('2026-01-01T00:00:00+00:00', $message->occurredAt);
        self::assertSame(0, $message->attempts);
    }

    public function testProcessedEventStoreIsDurableAndListenerScoped(): void
    {
        $processed = new SqliteProcessedEventStore($this->pdo, new Counters());

        self::assertFalse($processed->has('listener-a', 'evt-1'));

        $processed->mark('listener-a', 'evt-1');

        self::assertTrue($processed->has('listener-a', 'evt-1'));
        self::assertFalse($processed->has('listener-b', 'evt-1'));
        self::assertFalse($processed->has('listener-a', 'evt-2'));
    }

    public function testMarkingTheSameKeyTwiceIsIgnored(): void
    {
        $processed = new SqliteProcessedEventStore($this->pdo, new Counters());
        $processed->mark('listener', 'evt-1');
        $processed->mark('listener', 'evt-1');

        self::assertTrue($processed->has('listener', 'evt-1'));
    }

    /**
     * @param array<string, int|string|bool|null> $payload
     */
    private function event(string $id, array $payload = []): EventEnvelope
    {
        return new EventEnvelope($id, 'test.event', 'agg', $payload, '2026-01-01T00:00:00+00:00');
    }
}
