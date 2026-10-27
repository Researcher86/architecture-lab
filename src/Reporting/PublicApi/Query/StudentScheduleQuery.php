<?php

declare(strict_types=1);

namespace ArchitectureLab\Reporting\PublicApi\Query;

final readonly class StudentScheduleQuery
{
    public function __construct(public string $studentId)
    {
    }
}
