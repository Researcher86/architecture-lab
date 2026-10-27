<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

/** A durable (or in-memory) queue between an aggregate write and delivery. */
interface Outbox
{
    public function record(EventEnvelope $event): void;

    /**
     * @return list<OutboxMessage>
     */
    public function pending(int $limit): array;

    public function markDelivered(string $eventId): void;

    public function markFailed(string $eventId, string $error, bool $deadLetter): void;

    public function countPending(): int;

    public function countDeadLettered(): int;
}
