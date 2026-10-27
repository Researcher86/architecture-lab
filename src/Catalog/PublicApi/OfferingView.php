<?php

declare(strict_types=1);

namespace ArchitectureLab\Catalog\PublicApi;

use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;

/** A read model of the catalog that other contexts may see. */
final readonly class OfferingView
{
    public function __construct(
        public OfferingId $id,
        public string $title,
        public Money $price,
        public int $capacity,
        public int $reservedSeats,
        public string $startsAt,
    ) {
    }

    public function seatsLeft(): int
    {
        return $this->capacity - $this->reservedSeats;
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'offering_id' => $this->id->value,
            'title' => $this->title,
            'price_cents' => $this->price->cents,
            'currency' => $this->price->currency,
            'capacity' => $this->capacity,
            'reserved_seats' => $this->reservedSeats,
            'seats_left' => $this->seatsLeft(),
            'starts_at' => $this->startsAt,
        ];
    }
}
