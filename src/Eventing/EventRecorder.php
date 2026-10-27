<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

/** Creates an event with a deterministic id and timestamp. */
final readonly class EventRecorder
{
    public function __construct(
        private \ArchitectureLab\Shared\Port\IdGenerator $ids,
        private \ArchitectureLab\Shared\Port\Clock $clock,
    ) {
    }

    /**
     * @param array<string, int|string|bool|null> $payload
     */
    public function record(string $name, string $aggregateId, array $payload): EventEnvelope
    {
        return new EventEnvelope(
            id: $this->ids->next('evt'),
            name: $name,
            aggregateId: $aggregateId,
            payload: $payload,
            occurredAt: $this->clock->now()->format(DATE_ATOM),
        );
    }
}
