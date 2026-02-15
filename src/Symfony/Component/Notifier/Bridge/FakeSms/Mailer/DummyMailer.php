<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeSms\Mailer;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

class DummyMailer implements MailerInterface
{
    public ?RawMessage $sentEmail = null;

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->sentEmail = $message;
    }
}
