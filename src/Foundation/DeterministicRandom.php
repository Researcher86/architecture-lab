<?php

declare(strict_types=1);

namespace ArchitectureLab\Foundation;

use InvalidArgumentException;

/**
 * Reproducible pseudo-random values for datasets and property tests.
 *
 * Each value is a hash of the seed and an incrementing counter rather than the
 * state of a hand-written 64-bit generator: PHP integers are signed, and the
 * usual xorshift arithmetic silently turns into floats on overflow. Hashing
 * keeps the sequence reproducible and the implementation small. The generator
 * is not cryptographic and is never used for anything security-sensitive.
 */
final class DeterministicRandom
{
    /**
     * 15 hex digits = 60 bits, which fits comfortably in a signed 64-bit int.
     */
    private const HEX_DIGITS = 15;

    /**
     * 2^60, the size of the value space produced by nextInt().
     */
    private const SPACE = 1_152_921_504_606_846_976;

    private int $counter = 0;

    public function __construct(
        private readonly string $seed,
    ) {
    }

    /**
     * @return int A value in [0, 2^60 - 1].
     */
    public function nextInt(): int
    {
        $hex = substr(hash('xxh128', $this->seed . ':' . $this->counter++), 0, self::HEX_DIGITS);

        return (int) hexdec($hex);
    }

    /**
     * @return float A value in [0, 1).
     */
    public function nextFloat(): float
    {
        return $this->nextInt() / self::SPACE;
    }

    /**
     * @param int $min Inclusive lower bound.
     * @param int $max Inclusive upper bound, at most 2^60 - 1.
     */
    public function intBetween(int $min, int $max): int
    {
        if ($min < 0 || $max < $min || $max >= self::SPACE) {
            throw new InvalidArgumentException(sprintf(
                'Range [%d, %d] must satisfy 0 <= min <= max < %d',
                $min,
                $max,
                self::SPACE,
            ));
        }

        return $min + $this->nextInt() % ($max - $min + 1);
    }
}
