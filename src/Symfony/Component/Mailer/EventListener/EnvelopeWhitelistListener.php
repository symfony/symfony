<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;

/**
 * Manipulates the Envelope of a Message.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EnvelopeWhitelistListener implements EventSubscriberInterface
{
    private $sender;
    private $recipients;
    private $whitelist;

    /**
     * @param Address|string     $sender
     * @param (Address|string)[] $recipients
     * @param (Address|string)[] $whitelist
     */
    public function __construct($sender = null, array $recipients = null, array $whitelist = null)
    {
        if (null !== $sender) {
            $this->sender = Address::create($sender);
        }
        if (null !== $recipients) {
            $this->recipients = Address::createArray($recipients);
        }
        if (null !== $whitelist) {
            $this->whitelist = Address::createArray($whitelist);
        }
    }

    public function onMessage(MessageEvent $event): void
    {
        if ($this->sender) {
            $event->getEnvelope()->setSender($this->sender);
        }

        if ($this->whitelist) {
            array_push(
                $this->recipients, 
                array_intersect(
                    $event->getEnvelope()->getRecipients(), 
                    $this->whitelist
                )
            );
        }

        if ($this->recipients) {
            $event->getEnvelope()->setRecipients($this->recipients);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            // should be the last one to allow header changes by other listeners first
            MessageEvent::class => ['onMessage', -255],
        ];
    }
}
