<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\Infrastructure;

use ArchitectureLab\Billing\Application\Port\InvoiceRepository;
use ArchitectureLab\Billing\Domain\Invoice;
use ArchitectureLab\Billing\Domain\InvoiceStatus;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\InvoiceId;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\Port\DocumentStore;
use ArchitectureLab\Shared\StudentId;
use DateTimeImmutable;

final readonly class DocumentInvoiceRepository implements InvoiceRepository
{
    private const COLLECTION = 'billing_invoices';

    public function __construct(
        private DocumentStore $store,
        private Counters $counters,
    ) {
    }

    public function save(Invoice $invoice): void
    {
        $this->store->put(self::COLLECTION, $invoice->id()->value, [
            'id' => $invoice->id()->value,
            'enrollment_id' => $invoice->enrollmentId()->value,
            'student_id' => $invoice->studentId()->value,
            'offering_id' => $invoice->offeringId()->value,
            'amount_cents' => $invoice->amount()->cents,
            'currency' => $invoice->amount()->currency,
            'issued_at' => $invoice->issuedAt()->format(DATE_ATOM),
            'status' => $invoice->status()->value,
            'failure_reason' => $invoice->failureReason(),
            'decided_at' => $invoice->decidedAt()?->format(DATE_ATOM),
        ]);
        ++$this->counters->repositoryCalls;
    }

    public function get(InvoiceId $id): ?Invoice
    {
        $document = $this->store->get(self::COLLECTION, $id->value);
        ++$this->counters->repositoryCalls;

        return $document === null ? null : $this->hydrate($document);
    }

    public function findByEnrollment(EnrollmentId $enrollmentId): ?Invoice
    {
        foreach ($this->store->all(self::COLLECTION) as $document) {
            if (($document['enrollment_id'] ?? null) === $enrollmentId->value) {
                ++$this->counters->repositoryCalls;

                return $this->hydrate($document);
            }
        }

        ++$this->counters->repositoryCalls;

        return null;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function hydrate(array $document): Invoice
    {
        $invoice = new Invoice(
            new InvoiceId((string) $document['id']),
            new EnrollmentId((string) $document['enrollment_id']),
            new StudentId((string) $document['student_id']),
            new OfferingId((string) $document['offering_id']),
            new Money((int) $document['amount_cents'], (string) $document['currency']),
            new DateTimeImmutable((string) $document['issued_at']),
        );

        $status = InvoiceStatus::from((string) $document['status']);
        $decidedAt = $document['decided_at'] === null ? null : new DateTimeImmutable((string) $document['decided_at']);

        if ($status === InvoiceStatus::Paid && $decidedAt !== null) {
            $invoice->markPaid($decidedAt);
        }

        if ($status === InvoiceStatus::Failed && $decidedAt !== null) {
            $invoice->markFailed((string) ($document['failure_reason'] ?? 'unknown'), $decidedAt);
        }

        return $invoice;
    }
}
