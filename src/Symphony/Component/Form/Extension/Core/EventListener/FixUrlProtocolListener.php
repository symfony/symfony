<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Core\EventListener;

use Symphony\Component\Form\FormEvents;
use Symphony\Component\Form\FormEvent;
use Symphony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds a protocol to a URL if it doesn't already have one.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FixUrlProtocolListener implements EventSubscriberInterface
{
    private $defaultProtocol;

    /**
     * @param string|null $defaultProtocol The URL scheme to add when there is none or null to not modify the data
     */
    public function __construct(?string $defaultProtocol = 'http')
    {
        $this->defaultProtocol = $defaultProtocol;
    }

    public function onSubmit(FormEvent $event)
    {
        $data = $event->getData();

        if ($this->defaultProtocol && $data && !preg_match('~^[\w+.-]+://~', $data)) {
            $event->setData($this->defaultProtocol.'://'.$data);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(FormEvents::SUBMIT => 'onSubmit');
    }
}
