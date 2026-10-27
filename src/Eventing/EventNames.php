<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

/** The names are the published language between bounded contexts. */
final class EventNames
{
    public const COURSE_OFFERING_PUBLISHED = 'catalog.course_offering_published';
    public const SEAT_RESERVED = 'catalog.seat_reserved';
    public const SEAT_RELEASED = 'catalog.seat_released';
    public const ENROLLMENT_REQUESTED = 'enrollment.requested';
    public const ENROLLMENT_CONFIRMED = 'enrollment.confirmed';
    public const ENROLLMENT_CANCELLED = 'enrollment.cancelled';
    public const INVOICE_ISSUED = 'billing.invoice_issued';
    public const PAYMENT_SUCCEEDED = 'billing.payment_succeeded';
    public const PAYMENT_FAILED = 'billing.payment_failed';
    public const NOTIFICATION_SENT = 'notification.sent';

    private function __construct()
    {
    }
}
