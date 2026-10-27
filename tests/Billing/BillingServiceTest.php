<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Billing;

use ArchitectureLab\Billing\Application\BillingService;
use ArchitectureLab\Billing\Application\Port\PaymentGateway;
use ArchitectureLab\Billing\Application\Port\PaymentResult;
use ArchitectureLab\Billing\Domain\Invoice;
use ArchitectureLab\Billing\Infrastructure\DocumentInvoiceRepository;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Eventing\EventRecorder;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Persistence\InMemoryDocumentStore;
use ArchitectureLab\Persistence\InMemoryOutbox;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\FrozenClock;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\SequentialIdGenerator;
use ArchitectureLab\Shared\StudentId;
use PHPUnit\Framework\TestCase;

final class BillingServiceTest extends TestCase
{
    public function testIssueInvoiceAndChargeOnce(): void
    {
        $gateway = $this->gateway([new PaymentResult(true)]);
        [$billing, $outbox] = $this->service($gateway);

        $outcome = $billing->chargeForEnrollment(
            new EnrollmentId('enr-1'),
            new StudentId('s-1'),
            new OfferingId('arch-101'),
            new Money(12000),
        );

        self::assertTrue($outcome->paid);
        self::assertSame('inv-1', $outcome->invoiceId);
        self::assertSame(1, $gateway->calls);
        self::assertSame([
            EventNames::INVOICE_ISSUED,
            EventNames::PAYMENT_SUCCEEDED,
        ], array_map(static fn ($message): string => $message->name, $outbox->pending(10)));
    }

    public function testRetryReturnsTheExistingDecisionWithoutChargingAgain(): void
    {
        $gateway = $this->gateway([new PaymentResult(true)]);
        [$billing] = $this->service($gateway);
        $charge = fn (): \ArchitectureLab\Billing\PublicApi\PaymentOutcome => $billing->chargeForEnrollment(
            new EnrollmentId('enr-1'),
            new StudentId('s-1'),
            new OfferingId('arch-101'),
            new Money(12000),
        );

        $first = $charge();
        $second = $charge();

        self::assertSame($first->invoiceId, $second->invoiceId);
        self::assertTrue($second->paid);
        self::assertSame(1, $gateway->calls);
    }

    public function testFailedPaymentIsRecordedAndRetryDoesNotChargeAgain(): void
    {
        $gateway = $this->gateway([
            new PaymentResult(false, 'card_declined'),
            new PaymentResult(true),
        ]);
        [$billing, $outbox] = $this->service($gateway);

        $outcome = $billing->chargeForEnrollment(
            new EnrollmentId('enr-1'),
            new StudentId('s-1'),
            new OfferingId('arch-101'),
            new Money(12000),
        );
        $retry = $billing->chargeForEnrollment(
            new EnrollmentId('enr-1'),
            new StudentId('s-1'),
            new OfferingId('arch-101'),
            new Money(12000),
        );

        self::assertFalse($outcome->paid);
        self::assertSame('card_declined', $outcome->failureReason);
        self::assertFalse($retry->paid);
        self::assertSame('card_declined', $retry->failureReason);
        self::assertSame(1, $gateway->calls);

        $events = array_map(static fn ($message): string => $message->name, $outbox->pending(10));
        self::assertContains(EventNames::PAYMENT_FAILED, $events);
        self::assertNotContains(EventNames::PAYMENT_SUCCEEDED, $events);
    }

    public function testDifferentEnrollmentsGetDifferentInvoices(): void
    {
        $gateway = $this->gateway([new PaymentResult(true), new PaymentResult(true)]);
        [$billing] = $this->service($gateway);

        $first = $billing->chargeForEnrollment(
            new EnrollmentId('enr-1'),
            new StudentId('s-1'),
            new OfferingId('arch-101'),
            new Money(12000),
        );
        $second = $billing->chargeForEnrollment(
            new EnrollmentId('enr-2'),
            new StudentId('s-2'),
            new OfferingId('arch-101'),
            new Money(12000),
        );

        self::assertNotSame($first->invoiceId, $second->invoiceId);
        self::assertSame(2, $gateway->calls);
    }

    public function testInvoiceDomainTransitions(): void
    {
        $invoice = new Invoice(
            new \ArchitectureLab\Shared\InvoiceId('inv-1'),
            new EnrollmentId('enr-1'),
            new StudentId('s-1'),
            new OfferingId('arch-101'),
            new Money(12000),
            new \DateTimeImmutable('2026-02-01T10:00:00+00:00'),
        );

        self::assertSame(\ArchitectureLab\Billing\Domain\InvoiceStatus::Open, $invoice->status());

        $invoice->markPaid(new \DateTimeImmutable('2026-02-01T10:01:00+00:00'));

        self::assertSame(\ArchitectureLab\Billing\Domain\InvoiceStatus::Paid, $invoice->status());
        self::assertSame('inv-1', $invoice->id()->value);
        self::assertSame('enr-1', $invoice->enrollmentId()->value);

        $this->expectException(\DomainException::class);
        $invoice->markFailed('late', new \DateTimeImmutable());
    }

    /**
     * @param list<PaymentResult> $results
     */
    private function gateway(array $results): FakePaymentGateway
    {
        return new FakePaymentGateway($results);
    }

    /**
     * @return array{BillingService, InMemoryOutbox}
     */
    private function service(PaymentGateway $gateway): array
    {
        $store = new InMemoryDocumentStore(new Counters());
        $outbox = new InMemoryOutbox();
        $clock = new FrozenClock();
        $ids = new SequentialIdGenerator();

        return [
            new BillingService(
                new DocumentInvoiceRepository($store, new Counters()),
                $gateway,
                $store,
                $outbox,
                new EventRecorder($ids, $clock),
                $ids,
                $clock,
            ),
            $outbox,
        ];
    }
}

final class FakePaymentGateway implements PaymentGateway
{
    public int $calls = 0;

    /**
     * @param list<PaymentResult> $results
     */
    public function __construct(private array $results)
    {
    }

    public function charge(Invoice $invoice): PaymentResult
    {
        $result = $this->results[$this->calls] ?? new PaymentResult(false, 'no_more_results');
        ++$this->calls;

        return $result;
    }
}
