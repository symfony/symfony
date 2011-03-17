<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FixedFilterListener implements EventSubscriberInterface
{
    private $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = array_merge(array(
            'filterBoundDataFromClient' => array(),
            'filterBoundData' => array(),
            'filterSetData' => array(),
        ), $mapping);
    }

    public function filterBoundDataFromClient(FilterDataEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['filterBoundDataFromClient'][$data])) {
            $event->setData($this->mapping['filterBoundDataFromClient'][$data]);
        }
    }

    public function filterBoundData(FilterDataEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['filterBoundData'][$data])) {
            $event->setData($this->mapping['filterBoundData'][$data]);
        }
    }

    public function filterSetData(FilterDataEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['filterSetData'][$data])) {
            $event->setData($this->mapping['filterSetData'][$data]);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            Events::filterBoundDataFromClient,
            Events::filterBoundData,
            Events::filterSetData,
        );
    }
}