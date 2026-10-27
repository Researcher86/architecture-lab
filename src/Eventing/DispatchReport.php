<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

/** What one dispatch pass did. */
final class DispatchReport
{
    public int $delivered = 0;
    public int $failed = 0;
    public int $deadLettered = 0;
    public int $handled = 0;

    public function add(self $other): void
    {
        $this->delivered += $other->delivered;
        $this->failed += $other->failed;
        $this->deadLettered += $other->deadLettered;
        $this->handled += $other->handled;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'delivered' => $this->delivered,
            'failed' => $this->failed,
            'dead_lettered' => $this->deadLettered,
            'handled' => $this->handled,
        ];
    }
}
