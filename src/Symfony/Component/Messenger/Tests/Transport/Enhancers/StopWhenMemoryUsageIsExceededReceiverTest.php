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
use Symfony\Component\Messenger\Transport\Enhancers\StopWhenMemoryUsageIsExceededReceiver;

class StopWhenMemoryUsageIsExceededReceiverTest extends TestCase
{
    /**
     * @dataProvider memoryProvider
     */
    public function testReceiverStopsWhenMemoryLimitExceeded(int $memoryUsage, int $memoryLimit, bool $shouldStop)
    {
        $callable = function ($handler) {
            $handler(new Envelope(new DummyMessage('API')));
        };

        $decoratedReceiver = $this->getMockBuilder(CallbackReceiver::class)
            ->setConstructorArgs(array($callable))
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $decoratedReceiver->expects($this->once())->method('receive');
        if (true === $shouldStop) {
            $decoratedReceiver->expects($this->once())->method('stop');
        } else {
            $decoratedReceiver->expects($this->never())->method('stop');
        }

        $memoryResolver = function () use ($memoryUsage) {
            return $memoryUsage;
        };

        $memoryLimitReceiver = new StopWhenMemoryUsageIsExceededReceiver($decoratedReceiver, $memoryLimit, null, $memoryResolver);
        $memoryLimitReceiver->receive(function () {});
    }

    public function memoryProvider()
    {
        yield array(2048, 1024, true);
        yield array(1024, 1024, false);
        yield array(1024, 2048, false);
    }

    public function testReceiverLogsMemoryExceededWhenLoggerIsGiven()
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
            ->with('Receiver stopped due to memory limit of {limit} exceeded', array('limit' => 64 * 1024 * 1024));

        $memoryResolver = function () {
            return 70 * 1024 * 1024;
        };

        $memoryLimitReceiver = new StopWhenMemoryUsageIsExceededReceiver($decoratedReceiver, 64 * 1024 * 1024, $logger, $memoryResolver);
        $memoryLimitReceiver->receive(function () {});
    }
}
