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
use Symfony\Component\Form\FormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FixedFilterListener implements EventSubscriberInterface
{
    private $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = array_merge(array(
            'preBind' => array(),
            'onBind' => array(),
            'preSetData' => array(),
        ), $mapping);
    }

    public function preBind(FormEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['preBind'][$data])) {
            $event->setData($this->mapping['preBind'][$data]);
        }
    }

    public function onBind(FormEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['onBind'][$data])) {
            $event->setData($this->mapping['onBind'][$data]);
        }
    }

    public function preSetData(FormEvent $event)
    {
        $data = $event->getData();

        if (isset($this->mapping['preSetData'][$data])) {
            $event->setData($this->mapping['preSetData'][$data]);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_BIND => 'preBind',
            FormEvents::BIND => 'onBind',
            FormEvents::PRE_SET_DATA => 'preSetData',
        );
    }
}
