<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

/** One interested party for one event name. */
interface EventListener
{
    public function handle(EventEnvelope $event): void;
}
