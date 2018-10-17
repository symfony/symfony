<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Enhancers;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\CallbackReceiver;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Enhancers\StopWhenTimeLimitIsReachedReceiver;

class StopWhenTimeLimitIsReachedReceiverTest extends TestCase
{
    /**
     * @group time-sensitive
     */
    public function testReceiverStopsWhenTimeLimitIsReached()
    {
        $callable = function ($handler) {
            $handler(new Envelope(new DummyMessage('API')));
        };

        $decoratedReceiver = $this->getMockBuilder(CallbackReceiver::class)
            ->setConstructorArgs(array($callable))
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $decoratedReceiver->expects($this->once())->method('receive');
        $decoratedReceiver->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('Receiver stopped due to time limit of {timeLimit}s reached', array('timeLimit' => 1));

        $timeoutReceiver = new StopWhenTimeLimitIsReachedReceiver($decoratedReceiver, 1, $logger);
        $timeoutReceiver->receive(function () {
            sleep(2);
        });
    }
}
