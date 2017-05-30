<?php

namespace TreeHouse\EventStore\Upcasting;

use TreeHouse\EventStore\SerializedEvent;

class SimpleUpcasterChain implements UpcasterInterface
{
    /**
     * @var UpcasterInterface[]
     */
    protected $upcasters = [];

    /**
     * @param UpcasterInterface $upcaster
     */
    public function registerUpcaster(UpcasterInterface $upcaster)
    {
        $this->upcasters[] = $upcaster;
    }

    /**
     * Upcasts via a chain of upcasters.
     *
     * @return array|SerializedEvent[]
     */
    public function upcast(SerializedEvent $event, UpcastingContext $context)
    {
        $result = [];
        $events = [$event];

        foreach ($this->upcasters as $upcaster) {
            $result = [];

            foreach ($events as $event) {
                if ($upcaster->supports($event)) {
                    $upcasted = $upcaster->upcast($event, $context);

                    // TODO: deprecate support of non array values by upcasters
                    if (!is_array($upcasted)) {
                        $upcasted = [$upcasted];
                    }

                    foreach ($upcasted as $_upcasted) {
                        $result[] = $_upcasted;
                    }
                }
            }

            $events = $result;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function supports(SerializedEvent $event)
    {
        foreach ($this->upcasters as $upcaster) {
            if ($upcaster->supports($event)) {
                return true;
            }
        }

        return false;
    }
}
