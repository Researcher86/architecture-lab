<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Foundation;

use ArchitectureLab\Shared\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testAddsAmountsInTheSameCurrency(): void
    {
        $sum = Money::fromCents(1250)->add(Money::fromCents(750));

        self::assertTrue($sum->equals(Money::fromCents(2000)));
    }

    public function testSubtractsAmountsInTheSameCurrency(): void
    {
        $difference = Money::fromCents(2000)->subtract(Money::fromCents(1250));

        self::assertSame(750, $difference->cents);
    }

    public function testAddingDifferentCurrenciesIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot combine EUR and USD');

        Money::fromCents(100, 'EUR')->add(Money::fromCents(100, 'USD'));
    }

    public function testMoneyCannotBeNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(-1);
    }

    public function testCurrencyMustBeAThreeLetterCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(100, 'EURO');
    }

    public function testFormatsMinorUnits(): void
    {
        self::assertSame('EUR 12.34', Money::fromCents(1234)->format());
        self::assertSame('USD 0.05', Money::fromCents(5, 'USD')->format());
    }

    public function testRoundTripsThroughAnArray(): void
    {
        $restored = Money::fromArray(Money::fromCents(1999, 'GBP')->toArray());

        self::assertTrue($restored->equals(Money::fromCents(1999, 'GBP')));
    }

    public function testFromArrayUsesSafeDefaults(): void
    {
        $money = Money::fromArray([]);

        self::assertSame(0, $money->cents);
        self::assertSame('EUR', $money->currency);
    }

    public function testEqualityIncludesCurrency(): void
    {
        self::assertFalse(Money::fromCents(100, 'EUR')->equals(Money::fromCents(100, 'USD')));
        self::assertFalse(Money::fromCents(100)->equals(Money::fromCents(101)));
    }
}
