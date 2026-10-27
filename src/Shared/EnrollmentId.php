<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared;

use InvalidArgumentException;

/** Enrollment's identity for one student's attempt to take a course. */
final readonly class EnrollmentId
{
    public function __construct(public string $value)
    {
        if (trim($this->value) === '') {
            throw new InvalidArgumentException('Enrollment id cannot be empty');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
