<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FixedFilterListener implements EventSubscriberInterface
{
    private $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = array_merge(array(
            'onBindClientData' => array(),
            'onBindNormData' => array(),
            'onSetData' => array(),
        ), $mapping);
    }

    public function onBindClientData(FilterDataEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['onBindClientData'][$data])) {
            $event->setData($this->mapping['onBindClientData'][$data]);
        }
    }

    public function onBindNormData(FilterDataEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['onBindNormData'][$data])) {
            $event->setData($this->mapping['onBindNormData'][$data]);
        }
    }

    public function onSetData(FilterDataEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['onSetData'][$data])) {
            $event->setData($this->mapping['onSetData'][$data]);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::BIND_CLIENT_DATA => 'onBindClientData',
            FormEvents::BIND_NORM_DATA => 'onBindNormData',
            FormEvents::SET_DATA => 'onSetData',
        );
    }
}
