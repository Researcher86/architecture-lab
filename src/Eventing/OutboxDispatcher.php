<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

use Throwable;

/**
 * Drains the outbox once, one event at a time.
 *
 * Delivery is at-least-once. An event is marked delivered only after every
 * listener returned; a failure increments the attempt counter and either
 * leaves the event pending or moves it to the dead-letter state.
 */
final readonly class OutboxDispatcher
{
    public function __construct(
        private Outbox $outbox,
        private EventBus $bus,
        private int $maxAttempts = 3,
    ) {
        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be at least 1');
        }
    }

    public function dispatch(int $limit = 100): DispatchReport
    {
        $report = new DispatchReport();

        foreach ($this->outbox->pending($limit) as $message) {
            try {
                $report->handled += $this->bus->publish($message->event);
                $this->outbox->markDelivered($message->id);
                ++$report->delivered;
            } catch (Throwable $exception) {
                $attempts = $message->attempts + 1;
                $deadLetter = $attempts >= $this->maxAttempts;
                $this->outbox->markFailed($message->id, $exception->getMessage(), $deadLetter);
                ++$report->failed;

                if ($deadLetter) {
                    ++$report->deadLettered;
                }
            }
        }

        return $report;
    }
}
