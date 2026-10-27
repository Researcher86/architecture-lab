<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Persistence;

use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Persistence\InMemoryDocumentStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InMemoryDocumentStoreTest extends TestCase
{
    public function testPutsGetsAndListsDocuments(): void
    {
        $store = new InMemoryDocumentStore(new Counters());
        $store->put('courses', 'arch-101', ['title' => 'Architecture']);
        $store->put('courses', 'arch-102', ['title' => 'Testing']);

        self::assertSame(['title' => 'Architecture'], $store->get('courses', 'arch-101'));
        self::assertNull($store->get('courses', 'missing'));
        self::assertSame([
            ['title' => 'Architecture'],
            ['title' => 'Testing'],
        ], $store->all('courses'));
        self::assertSame([], $store->all('missing'));
    }

    public function testCollectionsAreIsolated(): void
    {
        $store = new InMemoryDocumentStore(new Counters());
        $store->put('left', 'id', ['value' => 1]);
        $store->put('right', 'id', ['value' => 2]);

        self::assertSame(['value' => 1], $store->get('left', 'id'));
        self::assertSame(['value' => 2], $store->get('right', 'id'));
    }

    public function testTransactionRollsBackOnFailure(): void
    {
        $store = new InMemoryDocumentStore(new Counters());
        $store->put('courses', 'arch-101', ['title' => 'Architecture']);

        try {
            $store->transactional(function () use ($store): void {
                $store->put('courses', 'arch-101', ['title' => 'Changed']);
                throw new RuntimeException('abort');
            });
            self::fail('The transaction should throw');
        } catch (RuntimeException) {
            self::assertSame(['title' => 'Architecture'], $store->get('courses', 'arch-101'));
        }
    }

    public function testTransactionReturnsTheWorkResult(): void
    {
        $store = new InMemoryDocumentStore(new Counters());

        $result = $store->transactional(fn (): string => 'value');

        self::assertSame('value', $result);
    }

    public function testCountsReadsAndWrites(): void
    {
        $counters = new Counters();
        $store = new InMemoryDocumentStore($counters);
        $store->put('courses', 'a', []);
        $store->put('courses', 'b', []);
        $store->get('courses', 'a');
        $store->all('courses');

        self::assertSame(2, $counters->storeWrites);
        self::assertSame(4, $counters->storeReads);
    }
}
