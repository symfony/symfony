<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Adds a protocol to a URL if it doesn't already have one.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FixUrlProtocolListener implements EventSubscriberInterface
{
    private $defaultProtocol;

    public function __construct($defaultProtocol = 'http')
    {
        $this->defaultProtocol = $defaultProtocol;
    }

    public function onBind(FormEvent $event)
    {
        $data = $event->getData();

        if ($this->defaultProtocol && $data && !preg_match('~^\w+://~', $data)) {
            $event->setData($this->defaultProtocol.'://'.$data);
        }
    }

    static public function getSubscribedEvents()
    {
        return array(FormEvents::BIND => 'onBind');
    }
}
