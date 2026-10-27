<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Support;

/** A shared, inspectable log for listener callbacks. */
final class CallLog
{
    /** @var list<string> */
    private array $entries = [];

    public function record(string $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * @return list<string>
     */
    public function entries(): array
    {
        return $this->entries;
    }
}
