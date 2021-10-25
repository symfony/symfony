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
    private $skipEmail = false;

    /**
     * @param string|null $defaultProtocol The URL scheme to add when there is none or null to not modify the data
     */
    public function __construct(?string $defaultProtocol = 'http')
    {
        $this->defaultProtocol = $defaultProtocol;
    }

    /**
     * @param bool $skipEmail the URL scheme is not added to values that match an email pattern
     */
    public function skipEmail(): void
    {
        $this->skipEmail = true;
    }

    public function onSubmit(FormEvent $event)
    {
        $data = $event->getData();

        if ($this->defaultProtocol && $data && \is_string($data) && !preg_match('~^[\w+.-]+://~', $data)) {
            if (preg_match('~^[^:/]+@[A-Za-z0-9-.]+\.[A-Za-z0-9]+$~', $data)) {
                if ($this->skipEmail) {
                    return;
                }
                trigger_deprecation('symfony/form', '5.4', 'Class "%s", will add a scheme to urls that looks like emails in 6.0. Call "setIgnoreEmail(true)"', __CLASS__);
            }
            $event->setData($this->defaultProtocol.'://'.$data);
        }
    }

    public static function getSubscribedEvents()
    {
        return [FormEvents::SUBMIT => 'onSubmit'];
    }
}
