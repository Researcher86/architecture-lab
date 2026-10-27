<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

/** One undelivered message plus the number of failed attempts. */
final readonly class OutboxMessage
{
    public function __construct(
        public string $id,
        public string $name,
        public string $aggregateId,
        /** @var array<string, int|string|bool|null> */
        public array $payload,
        public string $occurredAt,
        public int $attempts,
        public EventEnvelope $event,
    ) {
    }
}
