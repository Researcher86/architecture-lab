<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

final readonly class Violation
{
    public function __construct(
        public string $rule,
        public string $fromClass,
        public string $toClass,
        public string $path,
        public string $message,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'from' => $this->fromClass,
            'to' => $this->toClass,
            'path' => $this->path,
            'message' => $this->message,
        ];
    }
}
