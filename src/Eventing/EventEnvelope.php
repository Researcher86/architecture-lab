<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

use InvalidArgumentException;

/**
 * The immutable message that crosses a boundary.
 *
 * The payload is an array of scalar values on purpose: an integration event is
 * a published language, not a reference to a domain object. A subscriber in
 * another bounded context must not be able to mutate the producer's aggregate.
 */
final readonly class EventEnvelope
{
    /**
     * @param array<string, int|string|bool|null> $payload
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $aggregateId,
        public array $payload,
        public string $occurredAt,
        public int $version = 1,
    ) {
        if (trim($this->id) === '') {
            throw new InvalidArgumentException('Event id cannot be empty');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Event name cannot be empty');
        }

        if ($this->version < 1) {
            throw new InvalidArgumentException('Event version must be positive');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'aggregate_id' => $this->aggregateId,
            'payload' => $this->payload,
            'occurred_at' => $this->occurredAt,
            'version' => $this->version,
        ];
    }

    /**
     * @param array<string, mixed> $value
     */
    public static function fromArray(array $value): self
    {
        $payload = $value['payload'] ?? [];

        if (!is_array($payload)) {
            throw new InvalidArgumentException('Event payload must be an array');
        }

        /** @var array<string, int|string|bool|null> $payload */
        return new self(
            id: (string) ($value['id'] ?? ''),
            name: (string) ($value['name'] ?? ''),
            aggregateId: (string) ($value['aggregate_id'] ?? ''),
            payload: $payload,
            occurredAt: (string) ($value['occurred_at'] ?? ''),
            version: (int) ($value['version'] ?? 1),
        );
    }
}
