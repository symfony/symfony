<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;

class ExceptionDataCollectorTest extends TestCase
{
    public function testCollect()
    {
        $e = new \Exception('foo', 500);
        $c = new ExceptionDataCollector();
        $flattened = FlattenException::createFromThrowable($e);
        $trace = $flattened->getTrace();

        self::assertFalse($c->hasException());

        $c->collect(new Request(), new Response(), $e);

        self::assertTrue($c->hasException());
        self::assertEquals($flattened, $c->getException());
        self::assertSame('foo', $c->getMessage());
        self::assertSame(500, $c->getCode());
        self::assertSame('exception', $c->getName());
        self::assertSame($trace, $c->getTrace());
    }

    public function testCollectWithoutException()
    {
        $c = new ExceptionDataCollector();
        $c->collect(new Request(), new Response());

        self::assertFalse($c->hasException());
    }

    public function testReset()
    {
        $c = new ExceptionDataCollector();

        $c->collect(new Request(), new Response(), new \Exception());
        $c->reset();
        $c->collect(new Request(), new Response());

        self::assertFalse($c->hasException());
    }
}
