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
use Symfony\Component\Messenger\Transport\Enhancers\StopWhenMessageCountIsExceededReceiver;

class StopWhenMessageCountIsExceededReceiverTest extends TestCase
{
    /**
     * @dataProvider countProvider
     */
    public function testReceiverStopsWhenMaximumCountExceeded($max, $shouldStop)
    {
        $callable = function ($handler) {
            $handler(new Envelope(new DummyMessage('First message')));
            $handler(new Envelope(new DummyMessage('Second message')));
            $handler(new Envelope(new DummyMessage('Third message')));
        };

        $decoratedReceiver = $this->getMockBuilder(CallbackReceiver::class)
            ->setConstructorArgs(array($callable))
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $decoratedReceiver->expects($this->once())->method('receive');
        if (true === $shouldStop) {
            $decoratedReceiver->expects($this->any())->method('stop');
        } else {
            $decoratedReceiver->expects($this->never())->method('stop');
        }

        $maximumCountReceiver = new StopWhenMessageCountIsExceededReceiver($decoratedReceiver, $max);
        $maximumCountReceiver->receive(function () {});
    }

    public function countProvider()
    {
        yield array(1, true);
        yield array(2, true);
        yield array(3, true);
        yield array(4, false);
    }

    public function testReceiverDoesntIncreaseItsCounterWhenReceiveNullMessage()
    {
        $callable = function ($handler) {
            $handler(null);
            $handler(null);
            $handler(null);
            $handler(null);
        };

        $decoratedReceiver = $this->getMockBuilder(CallbackReceiver::class)
            ->setConstructorArgs(array($callable))
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $decoratedReceiver->expects($this->once())->method('receive');
        $decoratedReceiver->expects($this->never())->method('stop');

        $maximumCountReceiver = new StopWhenMessageCountIsExceededReceiver($decoratedReceiver, 1);
        $maximumCountReceiver->receive(function () {});
    }

    public function testReceiverLogsMaximumCountExceededWhenLoggerIsGiven()
    {
        $callable = function ($handler) {
            $handler(new Envelope(new DummyMessage('First message')));
        };

        $decoratedReceiver = $this->getMockBuilder(CallbackReceiver::class)
            ->setConstructorArgs(array($callable))
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $decoratedReceiver->expects($this->once())->method('receive');
        $decoratedReceiver->expects($this->once())->method('stop');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with(
                $this->equalTo('Receiver stopped due to maximum count of {count} exceeded'),
                $this->equalTo(array('count' => 1))
            );

        $maximumCountReceiver = new StopWhenMessageCountIsExceededReceiver($decoratedReceiver, 1, $logger);
        $maximumCountReceiver->receive(function () {});
    }
}
