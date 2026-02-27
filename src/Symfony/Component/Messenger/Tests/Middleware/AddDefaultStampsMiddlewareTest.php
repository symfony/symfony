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
use Symfony\Component\Messenger\Middleware\AddDefaultStampsMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DefaultStampsProviderDummyMessage;

final class AddDefaultStampsMiddlewareTest extends MiddlewareTestCase
{
    public function testSelfStampableStampsMiddleware()
    {
        $message = new DefaultStampsProviderDummyMessage('');
        $envelope = new Envelope($message);

        $decorator = new AddDefaultStampsMiddleware();

        $envelope = $decorator->handle($envelope, $this->getStackMock(true));

        $delayStamp = $envelope->last(DelayStamp::class);
        $this->assertNotNull($delayStamp);
        $this->assertSame(1, $delayStamp->getDelay());
    }

    public function testSelfStampableStampsMiddlewareIfStampExists()
    {
        $message = new DefaultStampsProviderDummyMessage('');
        $envelope = new Envelope($message, [new DelayStamp(5)]);

        $decorator = new AddDefaultStampsMiddleware();

        $envelope = $decorator->handle($envelope, $this->getStackMock(true));

        $delayStamp = $envelope->last(DelayStamp::class);
        $this->assertNotNull($delayStamp);
        $this->assertSame(5, $delayStamp->getDelay());
    }
}
