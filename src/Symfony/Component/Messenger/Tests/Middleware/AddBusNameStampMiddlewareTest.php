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
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class AddBusNameStampMiddlewareTest extends MiddlewareTestCase
{
    public function testItSendsTheMessageToAssignedSender()
    {
        $middleware = new AddBusNameStampMiddleware('the_bus_name');
        $envelope = new Envelope(new DummyMessage('the message'));

        $finalEnvelope = $middleware->handle($envelope, $this->getStackMock());
        /** @var BusNameStamp $busNameStamp */
        $busNameStamp = $finalEnvelope->last(BusNameStamp::class);
        $this->assertNotNull($busNameStamp);
        $this->assertSame('the_bus_name', $busNameStamp->getBusName());

        // the stamp should not be added over and over again
        $finalEnvelope = $middleware->handle($finalEnvelope, $this->getStackMock());
        $this->assertCount(1, $finalEnvelope->all(BusNameStamp::class));
    }
}
