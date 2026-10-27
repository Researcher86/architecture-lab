<?php

declare(strict_types=1);

namespace ArchitectureLab\Foundation;

/**
 * Human-readable byte sizes for reports and experiment prose.
 *
 * Binary units are used because every number in this lab comes from
 * memory_get_usage(), a byte count, not from a disk vendor's decimal label.
 */
final class ByteFormatter
{
    private const UNITS = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];

    public static function format(int $bytes): string
    {
        $value = (float) $bytes;
        $unit = 0;

        while (abs($value) >= 1024.0 && $unit < count(self::UNITS) - 1) {
            $value /= 1024.0;
            ++$unit;
        }

        if ($unit === 0) {
            return sprintf('%d B', $bytes);
        }

        return sprintf('%.1f %s', $value, self::UNITS[$unit]);
    }

    public static function formatSigned(int $bytes): string
    {
        return ($bytes > 0 ? '+' : '') . self::format($bytes);
    }
}
