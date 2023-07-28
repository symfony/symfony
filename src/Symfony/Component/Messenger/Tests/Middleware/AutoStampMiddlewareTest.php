<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\AutoStampMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\AutoStampedMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class AutoStampMiddlewareTest extends MiddlewareTestCase
{
    public function testHandleWithoutAutoStampAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        (new AutoStampMiddleware())->handle($envelope, $this->getStackMock());
        $this->assertCount(0, $envelope->all());
    }

    public function testHandleWithAutoStampAndNextMiddleware()
    {
        $message = new AutoStampedMessage();
        $envelope = new Envelope($message);

        $handledEnvelope = (new AutoStampMiddleware())->handle($envelope, $this->getStackMock());
        $this->assertCount(2, $handledEnvelope->all());

        $delayStamp = $handledEnvelope->last(DelayStamp::class);
        $this->assertInstanceOf(DelayStamp::class, $delayStamp);
        $this->assertSame(123, $delayStamp->getDelay());

        $validationStamp = $handledEnvelope->last(ValidationStamp::class);
        $this->assertInstanceOf(ValidationStamp::class, $validationStamp);
        $this->assertSame(['Default', 'Extra'], $validationStamp->getGroups());
    }
}
