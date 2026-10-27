<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Application\Port;

use RuntimeException;

/** Enrollment's word for a full course, not Catalog's. */
final class NoSeatsAvailable extends RuntimeException
{
}
