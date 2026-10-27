<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\Application\Port;

use ArchitectureLab\Billing\Domain\Invoice;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\InvoiceId;

interface InvoiceRepository
{
    public function save(Invoice $invoice): void;

    public function get(InvoiceId $id): ?Invoice;

    public function findByEnrollment(EnrollmentId $enrollmentId): ?Invoice;
}
