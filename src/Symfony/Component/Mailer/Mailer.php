<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer;

use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Mailer implements MailerInterface
{
    private $transport;
    private $bus;
    private $async;

    public function __construct(TransportInterface $transport, MessageBusInterface $bus = null, bool $async = false)
    {
        if ($async && null === $bus) {
            throw new \LogicException('You cannot send messages asynchronously as the Symfony Messenger Component is not installed. Try running "composer require symfony/messenger".');
        }

        $this->transport = $transport;
        $this->bus = $bus;
        $this->async = $async;
    }

    public function send(RawMessage $message, SmtpEnvelope $envelope = null): void
    {
        if (!$this->async) {
            $this->transport->send($message, $envelope);

            return;
        }

        $this->bus->dispatch(new SendEmailMessage($message, $envelope));
    }
}
