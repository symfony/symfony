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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\EnvelopeStamperMiddleware;
use Symfony\Component\Messenger\Middleware\Stamper\EnvelopeStamperInterface;

class EnvelopeStamperMiddlewareTest extends TestCase
{
    public function testHandleCallsStampers()
    {
        $stamper1 = $this->createMock(EnvelopeStamperInterface::class);
        $stamper2 = $this->createMock(EnvelopeStamperInterface::class);

        $envelopeOriginal = new Envelope(new \stdClass());
        $envelopeAfterStamper1 = new Envelope(new \stdClass());
        $envelopeAfterStamper2 = new Envelope(new \stdClass());

        $stamper1->expects($this->once())
            ->method('stampEnvelope')
            ->with($envelopeOriginal)
            ->willReturn($envelopeAfterStamper1);

        $stamper2->expects($this->once())
            ->method('stampEnvelope')
            ->with($envelopeAfterStamper1)
            ->willReturn($envelopeAfterStamper2);

        $stamperMiddleware = new EnvelopeStamperMiddleware([$stamper1, $stamper2]);
        $bus = new MessageBus([$stamperMiddleware]);
        $actualFinalEnvelope = $bus->dispatch($envelopeOriginal);
        $this->assertSame($envelopeAfterStamper2, $actualFinalEnvelope);
    }
}
