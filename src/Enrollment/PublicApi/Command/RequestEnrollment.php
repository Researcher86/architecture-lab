<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\PublicApi\Command;

/** A request from the delivery layer; it carries only primitives. */
final readonly class RequestEnrollment
{
    public function __construct(
        public string $studentId,
        public string $offeringId,
    ) {
    }
}
