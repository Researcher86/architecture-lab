<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\PublicApi\Exception;

use RuntimeException;

/** A course is full, so the request never became an enrollment. */
final class EnrollmentRejected extends RuntimeException
{
}
