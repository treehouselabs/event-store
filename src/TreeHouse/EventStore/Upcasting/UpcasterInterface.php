<?php

namespace TreeHouse\EventStore\Upcasting;

use TreeHouse\EventStore\SerializedEvent;

interface UpcasterInterface
{
    /**
     * @param SerializedEvent  $event
     * @param UpcastingContext $context
     *
     * @return SerializedEvent|array|SerializedEvent[]
     */
    public function upcast(SerializedEvent $event, UpcastingContext $context);

    /**
     * @param SerializedEvent $event
     *
     * @return bool
     */
    public function supports(SerializedEvent $event);
}
