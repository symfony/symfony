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

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Sends emails, synchronously or through a message bus.
 *
 * Implementations must support synchronous and asynchronous sending.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface MailerInterface
{
    /**
     * @throws TransportExceptionInterface
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): void;
}
