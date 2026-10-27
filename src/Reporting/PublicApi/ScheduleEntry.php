<?php

declare(strict_types=1);

namespace ArchitectureLab\Reporting\PublicApi;

final readonly class ScheduleEntry
{
    public function __construct(
        public string $enrollmentId,
        public string $offeringId,
        public string $courseTitle,
        public string $status,
    ) {
    }
}
