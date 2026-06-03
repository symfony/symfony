<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Middleware;

use Symfony\Component\Messenger\Bridge\AmazonSqs\MessageDeduplicationAwareInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\MessageGroupAwareInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Middleware\AddFifoStampMiddleware;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFifoStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class AddFifoStampTest extends MiddlewareTestCase
{
    public function testAddStampWithGroupIdOnly()
    {
        $middleware = new AddFifoStampMiddleware();
        $envelope = new Envelope(new WithMessageGroupIdMessage('groupId'));
        $finalEnvelope = $middleware->handle($envelope, $this->getStackMock());
        $stamp = $finalEnvelope->last(AmazonSqsFifoStamp::class);
        $this->assertInstanceOf(AmazonSqsFifoStamp::class, $stamp);
        $this->assertSame('groupId', $stamp->getMessageGroupId());
        $this->assertNull($stamp->getMessageDeduplicationId());
    }

    public function testHandleWithDeduplicationIdOnly()
    {
        $middleware = new AddFifoStampMiddleware();
        $envelope = new Envelope(new WithMessageDeduplicationIdMessage('deduplicationId'));
        $finalEnvelope = $middleware->handle($envelope, $this->getStackMock());
        $stamp = $finalEnvelope->last(AmazonSqsFifoStamp::class);
        $this->assertInstanceOf(AmazonSqsFifoStamp::class, $stamp);
        $this->assertSame('deduplicationId', $stamp->getMessageDeduplicationId());
        $this->assertNull($stamp->getMessageGroupId());
    }

    public function testHandleWithGroupIdAndDeduplicationId()
    {
        $middleware = new AddFifoStampMiddleware();
        $envelope = new Envelope(new WithMessageDeduplicationIdAndMessageGroupIdMessage('my_group', 'my_random_id'));
        $finalEnvelope = $middleware->handle($envelope, $this->getStackMock());
        $stamp = $finalEnvelope->last(AmazonSqsFifoStamp::class);
        $this->assertInstanceOf(AmazonSqsFifoStamp::class, $stamp);
        $this->assertSame('my_random_id', $stamp->getMessageDeduplicationId());
        $this->assertSame('my_group', $stamp->getMessageGroupId());
    }

    public function testHandleWithoutId()
    {
        $middleware = new AddFifoStampMiddleware();
        $envelope = new Envelope(new WithoutIdMessage());
        $finalEnvelope = $middleware->handle($envelope, $this->getStackMock());
        $stamp = $finalEnvelope->last(AmazonSqsFifoStamp::class);
        $this->assertNull($stamp);
    }
}

class WithMessageDeduplicationIdAndMessageGroupIdMessage implements MessageDeduplicationAwareInterface, MessageGroupAwareInterface
{
    public function __construct(
        private string $messageGroupId,
        private string $messageDeduplicationId,
    ) {
    }

    public function getMessageDeduplicationId(): ?string
    {
        return $this->messageDeduplicationId;
    }

    public function getMessageGroupId(): ?string
    {
        return $this->messageGroupId;
    }
}

class WithMessageDeduplicationIdMessage implements MessageDeduplicationAwareInterface
{
    public function __construct(
        private string $messageDeduplicationId,
    ) {
    }

    public function getMessageDeduplicationId(): ?string
    {
        return $this->messageDeduplicationId;
    }
}

class WithMessageGroupIdMessage implements MessageGroupAwareInterface
{
    public function __construct(
        private string $messageGroupId,
    ) {
    }

    public function getMessageGroupId(): ?string
    {
        return $this->messageGroupId;
    }
}
class WithoutIdMessage
{
}
