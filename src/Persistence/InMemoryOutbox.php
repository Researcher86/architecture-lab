<?php

declare(strict_types=1);

namespace ArchitectureLab\Persistence;

use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\Outbox;
use ArchitectureLab\Eventing\OutboxMessage;

/** An outbox that disappears with the process; useful for pure unit tests. */
final class InMemoryOutbox implements Outbox
{
    /** @var array<string, array{event: EventEnvelope, attempts: int, delivered: bool, dead: bool}> */
    private array $messages = [];

    public function record(EventEnvelope $event): void
    {
        $this->messages[$event->id] = [
            'event' => $event,
            'attempts' => 0,
            'delivered' => false,
            'dead' => false,
        ];
    }

    public function pending(int $limit): array
    {
        $pending = [];

        foreach ($this->messages as $message) {
            if ($message['delivered'] || $message['dead']) {
                continue;
            }

            $event = $message['event'];
            $pending[] = new OutboxMessage(
                id: $event->id,
                name: $event->name,
                aggregateId: $event->aggregateId,
                payload: $event->payload,
                occurredAt: $event->occurredAt,
                attempts: $message['attempts'],
                event: $event,
            );

            if (count($pending) >= $limit) {
                break;
            }
        }

        return $pending;
    }

    public function markDelivered(string $eventId): void
    {
        if (!isset($this->messages[$eventId])) {
            return;
        }

        $this->messages[$eventId]['delivered'] = true;
    }

    public function markFailed(string $eventId, string $error, bool $deadLetter): void
    {
        if (!isset($this->messages[$eventId])) {
            return;
        }

        ++$this->messages[$eventId]['attempts'];
        $this->messages[$eventId]['dead'] = $deadLetter;
    }

    public function countPending(): int
    {
        $count = 0;

        foreach ($this->messages as $message) {
            if (!$message['delivered'] && !$message['dead']) {
                ++$count;
            }
        }

        return $count;
    }

    public function countDeadLettered(): int
    {
        $count = 0;

        foreach ($this->messages as $message) {
            if ($message['dead']) {
                ++$count;
            }
        }

        return $count;
    }
}
