<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\SmtpEnvelope;
use Symfony\Component\Mime\RawMessage;

/**
 * Interface for all mailer transports.
 *
 * When sending emails, you should prefer MailerInterface implementations
 * as they allow asynchronous sending.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 4.3
 */
interface TransportInterface
{
    /**
     * @throws TransportExceptionInterface
     */
    public function send(RawMessage $message, SmtpEnvelope $envelope = null): ?SentMessage;
}
