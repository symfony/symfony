<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Tests\DataCollector;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Profiler\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExceptionDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $e = new \Exception('foo', 500);
        $c = new ExceptionDataCollector();
        $flattened = FlattenException::create($e);
        $trace = $flattened->getTrace();

        $data = $c->collect();
        $this->assertNull($data);

        $c->setException($e);
        $data = $c->collect();

        $this->assertInstanceOf('Symfony\Component\Profiler\ProfileData\ExceptionData', $data);

        $this->assertTrue($data->hasException());
        $this->assertEquals($flattened, $data->getException());
        $this->assertSame('foo', $data->getMessage());
        $this->assertSame(500, $data->getCode());
        $this->assertSame('exception', $data->getName());
        $this->assertSame($trace, $data->getTrace());
    }
}
