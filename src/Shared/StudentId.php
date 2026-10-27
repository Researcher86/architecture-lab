<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared;

use InvalidArgumentException;

/** A typed identifier; a student id is not an offering id by accident. */
final readonly class StudentId
{
    public function __construct(public string $value)
    {
        if (trim($this->value) === '') {
            throw new InvalidArgumentException('Student id cannot be empty');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
