<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

class MaxIdLengthAdapterTest extends TestCase
{
    public function testLongKey()
    {
        $cache = $this->getMockBuilder(MaxIdLengthAdapter::class)
            ->setConstructorArgs([str_repeat('-', 10)])
            ->onlyMethods(['doHave', 'doFetch', 'doDelete', 'doSave', 'doClear'])
            ->getMock();

        $cache->expects($this->exactly(2))
            ->method('doHave')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['----------:z5XrNUPebf0nPxQwjc6C1A:'],
                    ['----------:---------------------------------------'],
                ];

                $expectedArgs = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return false;
            })
        ;

        $cache->hasItem(str_repeat('-', 40));
        $cache->hasItem(str_repeat('-', 39));
    }

    public function testLongKeyVersioning()
    {
        $cache = $this->getMockBuilder(MaxIdLengthAdapter::class)
            ->setConstructorArgs([str_repeat('-', 26)])
            ->getMock();

        $cache
            ->method('doFetch')
            ->willReturn(['2:']);

        $reflectionClass = new \ReflectionClass(AbstractAdapter::class);

        $reflectionMethod = $reflectionClass->getMethod('getId');

        // No versioning enabled
        $this->assertEquals('--------------------------:------------', $reflectionMethod->invokeArgs($cache, [str_repeat('-', 12)]));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 12)])));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 23)])));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 40)])));

        $reflectionProperty = $reflectionClass->getProperty('versioningIsEnabled');
        $reflectionProperty->setValue($cache, true);

        // Versioning enabled
        $this->assertEquals('--------------------------:2:------------', $reflectionMethod->invokeArgs($cache, [str_repeat('-', 12)]));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 12)])));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 23)])));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 40)])));
    }

    public function testTooLongNamespace()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Namespace must be 26 chars max, 40 given ("----------------------------------------")');
        $this->getMockBuilder(MaxIdLengthAdapter::class)
            ->setConstructorArgs([str_repeat('-', 40)])
            ->getMock();
    }
}

abstract class MaxIdLengthAdapter extends AbstractAdapter
{
    public function __construct(string $ns)
    {
        $this->maxIdLength = 50;

        parent::__construct($ns);
    }
}
