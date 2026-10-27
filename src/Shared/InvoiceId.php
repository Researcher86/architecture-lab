<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared;

use InvalidArgumentException;

/** Billing's identity for the invoice that belongs to one enrollment. */
final readonly class InvoiceId
{
    public function __construct(public string $value)
    {
        if (trim($this->value) === '') {
            throw new InvalidArgumentException('Invoice id cannot be empty');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
