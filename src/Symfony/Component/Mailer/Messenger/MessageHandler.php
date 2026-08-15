<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Messenger;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MessageHandler
{
    public function __construct(
        private TransportInterface $transport,
    ) {
    }

    public function __invoke(SendEmailMessage $message): ?SentMessage
    {
        try {
            return $this->transport->send($message->getMessage(), $message->getEnvelope());
        } catch (RateLimitExceededException $e) {
            $retryDelay = max(0, ($e->getRetryAfter()->getTimestamp() - time()) * 1000);

            throw new RecoverableMessageHandlingException('Rate limit for mailer transport exceeded.', 0, $e, $retryDelay);
        }
    }
}
