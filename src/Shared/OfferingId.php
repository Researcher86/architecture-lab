<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared;

use InvalidArgumentException;

/** Catalog's identity for one scheduled course. */
final readonly class OfferingId
{
    public function __construct(public string $value)
    {
        if (trim($this->value) === '') {
            throw new InvalidArgumentException('Offering id cannot be empty');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
