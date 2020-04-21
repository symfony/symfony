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

use DateTime;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\AvailableAtStampMiddleware;
use Symfony\Component\Messenger\Stamp\AvailableAtStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class AvailableAtStampMiddlewareTest extends MiddlewareTestCase
{
    public function testAvailableAtAndDelayStampAreAdded()
    {
        $now = new DateTime();
        $availableAt = (clone $now)->modify('+1000 seconds');
        $availableAtStamp = new AvailableAtStamp($availableAt);

        $middleware = new AvailableAtStampMiddleware();

        $envelope = new Envelope(
            new DummyMessage('the message'),
            [
                $availableAtStamp,
            ]
        );

        $finalEnvelope = $middleware->handle($envelope, $this->getStackMock());

        /** @var AvailableAtStamp $availableAtStamp */
        $availableAtStamp = $finalEnvelope->last(AvailableAtStamp::class);
        $this->assertNotNull($availableAtStamp);
        $this->assertSame($availableAt, $availableAtStamp->getAvailableAt());

        /** @var DelayStamp $delayStamp */
        $delayStamp = $finalEnvelope->last(DelayStamp::class);
        $this->assertNotNull($delayStamp);
        $this->assertSame(1000 * 1000, $delayStamp->getDelay());

        // the stamp should not be added over and over again
        $finalEnvelope = $middleware->handle($finalEnvelope, $this->getStackMock());
        $this->assertCount(1, $finalEnvelope->all(AvailableAtStamp::class));
    }
}
