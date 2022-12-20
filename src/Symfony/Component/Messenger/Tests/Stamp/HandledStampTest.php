<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler;

class HandledStampTest extends TestCase
{
    public function testConstruct()
    {
        $stamp = new HandledStamp('some result', 'FooHandler::__invoke()');

        self::assertSame('some result', $stamp->getResult());
        self::assertSame('FooHandler::__invoke()', $stamp->getHandlerName());

        $stamp = new HandledStamp('some result', 'FooHandler::__invoke()');

        self::assertSame('some result', $stamp->getResult());
        self::assertSame('FooHandler::__invoke()', $stamp->getHandlerName());
    }

    public function testFromDescriptor()
    {
        $stamp = HandledStamp::fromDescriptor(new HandlerDescriptor(new DummyCommandHandler()), 'some_result');

        self::assertEquals(DummyCommandHandler::class.'::__invoke', $stamp->getHandlerName());
        self::assertSame('some_result', $stamp->getResult(), 'result is forwarded to construct');
    }
}
