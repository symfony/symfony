<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Mailer;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class DummyMailer implements MailerInterface
{
    private $sentMessage;

    public function send(RawMessage $message, Envelope $envelope = null): void
    {
        $this->sentMessage = $message;
    }

    public function getSentEmail(): RawMessage
    {
        return $this->sentMessage;
    }
}
