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
     * @param SerializedEvent $event
     * @param UpcastingContext $context
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

                    // TODO: remove support of non array values by upcasters in next major release
                    if (!is_array($upcasted)) {
                        @trigger_error(
                            'Upcasters need to return an array collection of upcasted events, ' .
                            'non array return values are deprecated and support will be removed in the next major release',
                            E_USER_DEPRECATED
                        );

                        $upcasted = [$upcasted];
                    }

                    array_push($result, ...$upcasted);
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
