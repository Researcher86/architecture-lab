<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\Application;

use ArchitectureLab\Billing\Application\Port\InvoiceRepository;
use ArchitectureLab\Billing\Application\Port\PaymentGateway;
use ArchitectureLab\Billing\Domain\Invoice;
use ArchitectureLab\Billing\Domain\InvoiceStatus;
use ArchitectureLab\Billing\PublicApi\BillingApi;
use ArchitectureLab\Billing\PublicApi\PaymentOutcome;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Eventing\EventRecorder;
use ArchitectureLab\Eventing\Outbox;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\InvoiceId;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\Port\Clock;
use ArchitectureLab\Shared\Port\IdGenerator;
use ArchitectureLab\Shared\Port\UnitOfWork;
use ArchitectureLab\Shared\StudentId;

/**
 * Billing's write side.
 *
 * The invoice is saved before the gateway is called, so a retry after a crash
 * can find it. The decision and its event are written in the same transaction.
 * Retrying the same enrollment never charges twice.
 */
final readonly class BillingService implements BillingApi
{
    public function __construct(
        private InvoiceRepository $invoices,
        private PaymentGateway $gateway,
        private UnitOfWork $unitOfWork,
        private Outbox $outbox,
        private EventRecorder $recorder,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function chargeForEnrollment(
        EnrollmentId $enrollmentId,
        StudentId $studentId,
        OfferingId $offeringId,
        Money $amount,
    ): PaymentOutcome {
        return $this->unitOfWork->transactional(function () use (
            $enrollmentId,
            $studentId,
            $offeringId,
            $amount,
        ): PaymentOutcome {
            $invoice = $this->invoices->findByEnrollment($enrollmentId);

            if ($invoice === null) {
                $invoice = new Invoice(
                    id: new InvoiceId($this->ids->next('inv')),
                    enrollmentId: $enrollmentId,
                    studentId: $studentId,
                    offeringId: $offeringId,
                    amount: $amount,
                    issuedAt: $this->clock->now(),
                );
                $this->invoices->save($invoice);
                $this->outbox->record($this->recorder->record(
                    EventNames::INVOICE_ISSUED,
                    $invoice->id()->value,
                    [
                        'invoice_id' => $invoice->id()->value,
                        'enrollment_id' => $enrollmentId->value,
                        'student_id' => $studentId->value,
                        'offering_id' => $offeringId->value,
                        'amount_cents' => $amount->cents,
                        'currency' => $amount->currency,
                    ],
                ));
            }

            if ($invoice->status() === InvoiceStatus::Paid) {
                return new PaymentOutcome(true, $invoice->id()->value);
            }

            if ($invoice->status() === InvoiceStatus::Failed) {
                return new PaymentOutcome(false, $invoice->id()->value, $invoice->failureReason());
            }

            $result = $this->gateway->charge($invoice);

            if ($result->paid) {
                $invoice->markPaid($this->clock->now());
                $this->invoices->save($invoice);
                $this->outbox->record($this->recorder->record(
                    EventNames::PAYMENT_SUCCEEDED,
                    $invoice->id()->value,
                    [
                        'invoice_id' => $invoice->id()->value,
                        'enrollment_id' => $enrollmentId->value,
                        'student_id' => $studentId->value,
                        'offering_id' => $offeringId->value,
                        'amount_cents' => $amount->cents,
                        'currency' => $amount->currency,
                    ],
                ));

                return new PaymentOutcome(true, $invoice->id()->value);
            }

            $reason = $result->reason ?? 'payment_failed';
            $invoice->markFailed($reason, $this->clock->now());
            $this->invoices->save($invoice);
            $this->outbox->record($this->recorder->record(
                EventNames::PAYMENT_FAILED,
                $invoice->id()->value,
                [
                    'invoice_id' => $invoice->id()->value,
                    'enrollment_id' => $enrollmentId->value,
                    'student_id' => $studentId->value,
                    'offering_id' => $offeringId->value,
                    'amount_cents' => $amount->cents,
                    'currency' => $amount->currency,
                    'reason' => $reason,
                ],
            ));

            return new PaymentOutcome(false, $invoice->id()->value, $reason);
        });
    }
}
