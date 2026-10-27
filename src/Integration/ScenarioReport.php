<?php

declare(strict_types=1);

namespace ArchitectureLab\Integration;

final readonly class ScenarioReport
{
    /**
     * @param array<string, mixed> $numbers
     * @param array<string, array{ok: bool, detail: string}> $checks
     */
    public function __construct(
        public string $mode,
        public string $seed,
        public array $numbers,
        public array $checks,
    ) {
    }

    public function ok(): bool
    {
        foreach ($this->checks as $check) {
            if (!$check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function failures(): array
    {
        $failures = [];

        foreach ($this->checks as $name => $check) {
            if (!$check['ok']) {
                $failures[] = sprintf('%s: %s', $name, $check['detail']);
            }
        }

        return $failures;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'seed' => $this->seed,
            'numbers' => $this->numbers,
            'checks' => $this->checks,
            'ok' => $this->ok(),
        ];
    }
}
