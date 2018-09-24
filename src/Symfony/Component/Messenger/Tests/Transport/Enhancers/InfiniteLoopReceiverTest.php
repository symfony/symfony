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
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Tests\Fixtures\CallbackReceiver;
use Symfony\Component\Messenger\Transport\Enhancers\InfiniteLoopReceiver;

class InfiniteLoopReceiverTest extends TestCase
{
    public function testReceiverReceivesUntilStopIsCalled()
    {
        $i = 0;
        $decoratedReceiver = null;
        $receiver = new CallbackReceiver(function ($handler) use (&$i, &$decoratedReceiver) {
            $i += 1;
            if ($i === 3) {
                $decoratedReceiver->stop();
            }
        });

        $decoratedReceiver = new InfiniteLoopReceiver($receiver);
        $decoratedReceiver->receive(function() {});
        $this->assertEquals(3, $i);
    }

    public function testReceiverDelegatesStopToInnerReceiver()
    {
        $decoratedReceiver = null;
        $receiver = new class($decoratedReceiver) implements ReceiverInterface {
            public $wasStopped = 0;
            private $decoratedReceiver;

            public function __construct(&$decoratedReceiver) {
                $this->decoratedReceiver = &$decoratedReceiver;
            }

            public function receive(callable $handle): void {
                $this->decoratedReceiver->stop();
            }
            public function stop(): void {
                $this->wasStopped += 1;
            }
        };
        $decoratedReceiver = new InfiniteLoopReceiver($receiver);
        $decoratedReceiver->receive(function() {});
        $this->assertEquals(1, $receiver->wasStopped);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage test
     */
    public function testReceiverRethrowsAnyExceptions()
    {
        $decoratedReceiver = null;
        $receiver = new CallbackReceiver(function ($handler) {
            throw new \Exception('test');
        });

        $decoratedReceiver = new InfiniteLoopReceiver($receiver);
        $decoratedReceiver->receive(function() {});
    }
}
