<?php

namespace TreeHouse\EventStore;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use TreeHouse\EventStore\Upcasting\SimpleUpcasterChain;
use TreeHouse\EventStore\Upcasting\UpcasterAwareInterface;
use TreeHouse\EventStore\Upcasting\UpcasterInterface;
use TreeHouse\EventStore\Upcasting\UpcastingContext;
use TreeHouse\Serialization\SerializerInterface;

class DBALEventStore implements UpcasterAwareInterface
{
    /**
     * @var UpcasterInterface
     */
    protected $upcaster;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EventFactory
     */
    private $eventFactory;

    /**
     * @param Connection          $connection
     * @param SerializerInterface $serializer
     * @param EventFactory        $eventFactory
     */
    public function __construct(Connection $connection, SerializerInterface $serializer, EventFactory $eventFactory)
    {
        $this->connection = $connection;
        $this->serializer = $serializer;
        $this->eventFactory = $eventFactory;
        $this->upcaster = new SimpleUpcasterChain();
    }

    /**
     * @inheritdoc
     */
    public function getStream($id)
    {
        $stream = $this->getPartialStream($id, 0);

        if (!$stream->count()) {
            throw new EventStreamNotFoundException($id);
        }

        return $stream;
    }

    /**
     * @inheritdoc
     */
    public function getPartialStream($id, $fromVersion, $toVersion = null)
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('uuid, name, payload, payload_version, version, datetime_created')
            ->from('event_store')
            ->where('uuid = :uuid')
            ->andWhere('version > :version_from')
            ->orderBy('version', 'ASC')

            ->setParameter('uuid', $id)
            ->setParameter('version_from', $fromVersion)
        ;

        if ($toVersion) {
            $qb
                ->andWhere('version <= :version_to')
                ->setParameter('version_to', $toVersion)
            ;
        }

        $eventsFromStore = $qb->execute()->fetchAll();

        return $this->upcastAndDeserialize($eventsFromStore, $id, $fromVersion);
    }

    /**
     * @inheritdoc
     */
    public function append(EventStreamInterface $eventStream)
    {
        $this->connection->beginTransaction();

        try {
            /** @var $event Event */
            foreach ($eventStream as $event) {
                $this->connection->insert(
                    'event_store',
                    [
                        'uuid' => $event->getId(),
                        'name' => $event->getName(),
                        'payload' => $this->serializer->serialize(
                            $event->getPayload()
                        ),
                        'payload_version' => $event->getPayloadVersion(),
                        'version' => $event->getVersion(),
                        'datetime_created' => $event->getDate()->format('Y-m-d H:i:s'),
                    ]
                );
            }

            $this->connection->commit();
        } catch (DBALException $exception) {
            $this->connection->rollBack();

            throw new EventStoreException($exception->getMessage());
        }
    }

    public function setUpcaster(UpcasterInterface $upcaster)
    {
        $this->upcaster = $upcaster;
    }

    /**
     * @param iterable $eventsFromStore
     * @param mixed $id
     * @param int $fromVersion
     *
     * @return EventStream
     */
    private function upcastAndDeserialize($eventsFromStore, $id, $fromVersion)
    {
        $eventsForStream = [];
        foreach ($eventsFromStore as $event) {
            $serializedEvent = new SerializedEvent(
                $event['uuid'],
                $event['name'],
                $event['payload'],
                (int) $event['payload_version'],
                (int) $event['version'],
                new DateTime($event['datetime_created'])
            );

            if ($this->upcaster->supports($serializedEvent)) {
                $eventsForStreamForContext = new EventStream($eventsForStream);

                // fix for partial streams (upcasters expect full stream)
                if ($fromVersion > 0) {
                    $eventsForStreamForContext = $this->getPartialStream($id, 0, $serializedEvent->getVersion() - 1);
                }

                $context = new UpcastingContext($eventsForStreamForContext, $this->serializer);

                $serializedEvent = $this->upcaster->upcast($serializedEvent, $context);
            }

            $eventsForStream[] = $this->eventFactory->createFromSerializedEvent($serializedEvent);
        }

        return new EventStream($eventsForStream);
    }
}
