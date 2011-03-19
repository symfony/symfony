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
            'filterBoundClientData' => array(),
            'filterBoundNormData' => array(),
            'filterSetData' => array(),
        ), $mapping);
    }

    public function filterBoundClientData(FilterDataEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['filterBoundClientData'][$data])) {
            $event->setData($this->mapping['filterBoundClientData'][$data]);
        }
    }

    public function filterBoundNormData(FilterDataEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['filterBoundNormData'][$data])) {
            $event->setData($this->mapping['filterBoundNormData'][$data]);
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
            Events::filterBoundClientData,
            Events::filterBoundNormData,
            Events::filterSetData,
        );
    }
}