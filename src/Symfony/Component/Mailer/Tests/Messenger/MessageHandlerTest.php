<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Messenger\MessageHandler;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimit;

class MessageHandlerTest extends TestCase
{
    public function testRateLimitedSendIsMarkedForRetry()
    {
        $exception = new RateLimitExceededException(new RateLimit(0, new \DateTimeImmutable('+30 seconds'), false, 2));
        $transport = new class($exception) implements TransportInterface {
            public function __construct(private RateLimitExceededException $exception)
            {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw $this->exception;
            }

            public function __toString(): string
            {
                return 'fake://';
            }
        };

        $handler = new MessageHandler($transport);
        $envelope = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);

        try {
            $handler(new SendEmailMessage(new RawMessage(''), $envelope));
            $this->fail('The rate limited send should not have succeeded.');
        } catch (RecoverableMessageHandlingException $e) {
            $this->assertSame($exception, $e->getPrevious());
            $this->assertGreaterThan(0, $e->getRetryDelay());
            $this->assertLessThanOrEqual(30000, $e->getRetryDelay());
        }
    }
}
