<?php

namespace TreeHouse\EventStore;

use TreeHouse\Serialization\SerializableInterface;

/**
 * @deprecated will be removed in 2.0
 */
interface MutableEventStoreInterface extends EventStoreInterface
{
    /**
     * @param $aggregateId
     * @param $version
     *
     * @deprecated will be removed in 2.0
     */
    public function remove($aggregateId, $version);

    /**
     * @param $aggregateId
     * @param $version
     * @param Event[] $events
     *
     * @deprecated will be removed in 2.0
     */
    public function insertBefore($aggregateId, $version, array $events);

    /**
     * @param Event                 $originalEvent
     * @param SerializableInterface $payload
     *
     * @deprecated will be removed in 2.0
     */
    public function updateEventPayload(Event $originalEvent, SerializableInterface $payload);
}
