<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\ChainHandler;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class ChainHandlerTest extends TestCase
{
    public function testItCallsTheHandlers()
    {
        $message = new DummyMessage('Hey');

        $handler1 = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $handler1
            ->expects($this->once())
            ->method('__invoke')
            ->with($message)
        ;
        $handler2 = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $handler2
            ->expects($this->once())
            ->method('__invoke')
            ->with($message)
        ;

        (new ChainHandler(array($handler1, $handler2)))($message);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage A collection of message handlers requires at least one handler.
     */
    public function testInvalidArgumentExceptionOnEmptyHandlers()
    {
        new ChainHandler(array());
    }
}
