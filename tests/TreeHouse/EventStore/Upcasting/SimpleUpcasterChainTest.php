<?php

namespace TreeHouse\EventStore\Upcasting;

use TreeHouse\EventStore\SerializedEvent;
use TreeHouse\EventStore\Tests\DummyUpcaster;

class SimpleUpcasterChainTest extends \PHPUnit_Framework_TestCase
{
    const UUID = '04928cbb-7062-4f4a-916f-105b43c54606';

    /**
     * @test
     */
    public function it_chains_upcasters()
    {
        $event = [new SerializedEvent(self::UUID, 'Foo', ['key' => 'value'], 2, 1, new \DateTime('2017-05-29 19:00:00'))];

        $multipleEvents = [
            new SerializedEvent(self::UUID, 'Foo', [], 3, 1, new \DateTime('2017-05-29 19:00:00')),
            new SerializedEvent(self::UUID, 'Spliced', ['key' => 'value'], 1, 1,new \DateTime('2017-05-29 19:00:00')),
        ];

        $upcasterChain = new SimpleUpcasterChain();

        // Will always return false, validates the chain loop does not break when an upcaster does not support
        // the given event.
        $upcasterChain->registerUpcaster(
            new DummyUpcaster(function (SerializedEvent $e) {
                return false;
            }, null)
        );

        $upcasterChain->registerUpcaster(
            new DummyUpcaster(function (SerializedEvent $e) {
                return true;
            }, $event)
        );
        $upcasterChain->registerUpcaster(new DummyUpcaster(function (SerializedEvent $e) {
            return $e->getName() === 'Foo' && $e->getPayloadVersion() === 2;
        }, $multipleEvents));

        $events = $upcasterChain->upcast(
            $this->prophesize(SerializedEvent::class)->reveal(),
            $this->prophesize(UpcastingContext::class)->reveal()
        );

        $this->assertInternalType('array', $events);
        $this->assertContainsOnlyInstancesOf(SerializedEvent::class, $events);

        $this->assertEquals([], $events[0]->getPayload());
        $this->assertEquals(3, $events[0]->getPayloadVersion());

        $this->assertEquals('Spliced', $events[1]->getName());
        $this->assertEquals(['key' => 'value'], $events[1]->getPayload());
        $this->assertEquals(1, $events[1]->getPayloadVersion());
    }

    /**
     * @test
     */
    public function it_supports_non_collection_return_from_upcaster()
    {
        $event = new SerializedEvent(self::UUID, 'Foo', ['key' => 'value'], 2, 1, new \DateTime('2017-05-29 19:00:00'));
        $event2 = new SerializedEvent(self::UUID, 'Foo', [], 3, 1, new \DateTime('2017-05-29 19:00:00'));

        $upcasterChain = new SimpleUpcasterChain();
        $upcasterChain->registerUpcaster(
            new DummyUpcaster(function (SerializedEvent $e) {
                return true;
            }, $event)
        );
        $upcasterChain->registerUpcaster(new DummyUpcaster(function (SerializedEvent $e) {
            return $e->getName() === 'Foo' && $e->getPayloadVersion() === 2;
        }, $event2));

        $upcastedEvent = $upcasterChain->upcast(
            $this->prophesize(SerializedEvent::class)->reveal(),
            $this->prophesize(UpcastingContext::class)->reveal()
        );

        $this->assertEquals([$event2], $upcastedEvent);
    }
}
