<?php

declare(strict_types=1);

namespace ArchitectureLab\Reporting\PublicApi;

final readonly class OfferingCapacityView
{
    public function __construct(
        public string $offeringId,
        public string $title,
        public int $capacity,
        public int $reservedSeats,
        public int $pending,
        public int $confirmed,
        public int $cancelled,
    ) {
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->reservedSeats);
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'offering_id' => $this->offeringId,
            'title' => $this->title,
            'capacity' => $this->capacity,
            'reserved_seats' => $this->reservedSeats,
            'seats_left' => $this->seatsLeft(),
            'pending' => $this->pending,
            'confirmed' => $this->confirmed,
            'cancelled' => $this->cancelled,
        ];
    }
}
