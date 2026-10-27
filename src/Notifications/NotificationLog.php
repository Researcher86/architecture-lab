<?php

declare(strict_types=1);

namespace ArchitectureLab\Notifications;

use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\EventListener;
use ArchitectureLab\Eventing\EventNames;

/**
 * A notification log instead of an email server.
 *
 * Keeping the side effect local makes duplicate delivery visible: the log
 * records how many notifications were actually sent, not how many events the
 * bus claims to have delivered.
 */
final class NotificationLog implements EventListener
{
    /** @var list<array<string, string>> */
    private array $notifications = [];

    public function handle(EventEnvelope $event): void
    {
        if ($event->name !== EventNames::ENROLLMENT_CONFIRMED && $event->name !== EventNames::ENROLLMENT_CANCELLED) {
            return;
        }

        $this->notifications[] = [
            'student_id' => (string) $event->payload['student_id'],
            'enrollment_id' => (string) $event->payload['enrollment_id'],
            'course_title' => (string) $event->payload['course_title'],
            'template' => $event->name === EventNames::ENROLLMENT_CONFIRMED ? 'enrollment_confirmed' : 'enrollment_cancelled',
            'reason' => (string) ($event->payload['reason'] ?? ''),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public function all(): array
    {
        return $this->notifications;
    }

    public function count(): int
    {
        return count($this->notifications);
    }
}
