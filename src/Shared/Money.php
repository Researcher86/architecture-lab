<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared;

use InvalidArgumentException;

/**
 * A money amount in minor units.
 *
 * The lab never uses floats for money: 19.99 is not representable exactly, and
 * payment rounding is not the subject of this project. The currency is part of
 * the value, so adding EUR to USD fails instead of silently producing a number.
 */
final readonly class Money
{
    public function __construct(
        public int $cents,
        public string $currency = 'EUR',
    ) {
        if ($this->cents < 0) {
            throw new InvalidArgumentException('Money cannot be negative');
        }

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid currency: %s', $this->currency));
        }
    }

    public static function fromCents(int $cents, string $currency = 'EUR'): self
    {
        return new self($cents, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function format(): string
    {
        return sprintf('%s %.2f', $this->currency, $this->cents / 100);
    }

    /**
     * @return array{cents: int, currency: string}
     */
    public function toArray(): array
    {
        return ['cents' => $this->cents, 'currency' => $this->currency];
    }

    /**
     * @param array{cents?: int, currency?: string} $value
     */
    public static function fromArray(array $value): self
    {
        return new self((int) ($value['cents'] ?? 0), (string) ($value['currency'] ?? 'EUR'));
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(sprintf(
                'Cannot combine %s and %s',
                $this->currency,
                $other->currency,
            ));
        }
    }
}
