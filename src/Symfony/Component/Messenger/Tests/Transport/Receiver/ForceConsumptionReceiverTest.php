<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Receiver;

use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\CallbackReceiver;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Receiver\ForceConsumptionReceiver;

class ForceConsumptionReceiverTest extends TestCase
{
    /**
     * @dataProvider logProvider
     */
    public function testReceiverDoesNotStopWhenExceptionIsThrown(bool $isLoggable)
    {
        $callable = function ($handler) {
            $handler(new Envelope(new DummyMessage('API')));
        };

        $decoratedReceiver = $this->getMockBuilder(CallbackReceiver::class)
            ->setConstructorArgs(array($callable))
            ->enableProxyingToOriginalMethods()
            ->getMock()
        ;

        $logger = null;
        if ($isLoggable) {
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects($this->once())->method('alert')
                ->with(
                    $this->equalTo('Receiver reached an exception: "{message}"'),
                    $this->equalTo(array('message' => 'my exception'))
                );
        }

        $decoratedReceiver->expects($this->exactly(2))->method('receive');

        $timeoutReceiver = new ForceConsumptionReceiver($decoratedReceiver, $logger);
        $timeoutReceiver->receive(
            function () {
                throw new Exception('my exception');
            }
        );

        $timeoutReceiver->receive(function () {});
    }

    public function logProvider()
    {
        return array(
            'with log' => array(true),
            'without log' => array(false),
        );
    }
}
