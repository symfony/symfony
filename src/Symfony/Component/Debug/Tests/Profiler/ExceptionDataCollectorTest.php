<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Tests\Profiler;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\Profiler\ExceptionDataCollector;
use Symfony\Component\EventDispatcher\Event;

class ExceptionDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $e = new \Exception('foo', 500);
        $c = new ExceptionDataCollector();
        $flattened = FlattenException::create($e);
        $trace = $flattened->getTrace();

        $data = $c->getCollectedData();
        $this->assertNull($data);

        $c->onException(new CustomExceptionEvent($e));
        $data = $c->getCollectedData();

        $this->assertInstanceOf('Symfony\Component\Debug\Profiler\ExceptionData', $data);

        $this->assertTrue($data->hasException());
        $this->assertEquals($flattened, $data->getException());
        $this->assertSame('foo', $data->getMessage());
        $this->assertSame(500, $data->getCode());
        $this->assertSame(500, $data->getStatusCode());
        $this->assertSame($trace, $data->getTrace());
    }
}

class CustomExceptionEvent extends Event
{
    private $exception;

    public function __construct(\Exception $exception)
    {
        $this->exception = $exception;
    }

    public function getException()
    {
        return $this->exception;
    }
}
