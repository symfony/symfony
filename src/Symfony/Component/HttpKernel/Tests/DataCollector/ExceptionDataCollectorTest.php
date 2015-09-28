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

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ExceptionFlattener;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExceptionDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getData
     */
    public function testCollect($c, $e, $flattened)
    {
        $trace = $flattened->getTrace();

        $this->assertFalse($c->hasException());

        $c->collect(new Request(), new Response(), $e);

        $this->assertTrue($c->hasException());
        $this->assertEquals($flattened, $c->getException());
        $this->assertSame('foo', $c->getMessage());
        $this->assertSame(500, $c->getCode());
        $this->assertSame('exception', $c->getName());
        $this->assertEquals($trace, $c->getTrace());
    }

    public function getData()
    {
        $e = new \Exception('foo', 500);
        $flattener = new ExceptionFlattener();

        return array(
            array(new ExceptionDataCollector(), $e, FlattenException::create($e)),
            array(new ExceptionDataCollector($flattener), $e, $flattener->flatten($e)),
        );
    }
}
