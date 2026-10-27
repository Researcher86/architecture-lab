<?php

declare(strict_types=1);

namespace ArchitectureLab\Catalog\Domain;

use DomainException;

/** The aggregate's own refusal; adapters translate it at the context edge. */
final class SeatsExhausted extends DomainException
{
}
