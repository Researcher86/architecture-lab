<?php

declare(strict_types=1);

namespace ArchitectureLab\Catalog\Domain;

use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * Catalog's aggregate: one scheduled course and the seats it owns.
 *
 * The aggregate enforces the capacity invariant. No SQL, no event bus and no
 * other context appears here; the only collaborators are value objects.
 */
final class CourseOffering
{
    private int $reservedSeats = 0;

    public function __construct(
        private readonly OfferingId $id,
        private readonly string $title,
        private readonly Money $price,
        private readonly DateTimeImmutable $startsAt,
        private readonly int $capacity,
    ) {
        if (trim($this->title) === '') {
            throw new InvalidArgumentException('Course title cannot be empty');
        }

        if ($this->capacity < 1) {
            throw new InvalidArgumentException('A course offering needs at least one seat');
        }
    }

    public function reserveSeat(): void
    {
        if ($this->reservedSeats >= $this->capacity) {
            throw new SeatsExhausted(sprintf(
                'Offering %s has no seats left (%d/%d)',
                $this->id->value,
                $this->reservedSeats,
                $this->capacity,
            ));
        }

        ++$this->reservedSeats;
    }

    public function releaseSeat(): void
    {
        if ($this->reservedSeats === 0) {
            throw new LogicException(sprintf('Offering %s has no reserved seat to release', $this->id->value));
        }

        --$this->reservedSeats;
    }

    public function id(): OfferingId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function reservedSeats(): int
    {
        return $this->reservedSeats;
    }

    public function seatsLeft(): int
    {
        return $this->capacity - $this->reservedSeats;
    }
}
