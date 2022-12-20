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
        $cache = self::getMockBuilder(MaxIdLengthAdapter::class)
            ->setConstructorArgs([str_repeat('-', 10)])
            ->setMethods(['doHave', 'doFetch', 'doDelete', 'doSave', 'doClear'])
            ->getMock();

        $cache->expects(self::exactly(2))
            ->method('doHave')
            ->withConsecutive(
                [self::equalTo('----------:nWfzGiCgLczv3SSUzXL3kg:')],
                [self::equalTo('----------:---------------------------------------')]
            );

        $cache->hasItem(str_repeat('-', 40));
        $cache->hasItem(str_repeat('-', 39));
    }

    public function testLongKeyVersioning()
    {
        $cache = self::getMockBuilder(MaxIdLengthAdapter::class)
            ->setConstructorArgs([str_repeat('-', 26)])
            ->getMock();

        $cache
            ->method('doFetch')
            ->willReturn(['2:']);

        $reflectionClass = new \ReflectionClass(AbstractAdapter::class);

        $reflectionMethod = $reflectionClass->getMethod('getId');
        $reflectionMethod->setAccessible(true);

        // No versioning enabled
        self::assertEquals('--------------------------:------------', $reflectionMethod->invokeArgs($cache, [str_repeat('-', 12)]));
        self::assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 12)])));
        self::assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 23)])));
        self::assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 40)])));

        $reflectionProperty = $reflectionClass->getProperty('versioningIsEnabled');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($cache, true);

        // Versioning enabled
        self::assertEquals('--------------------------:2:------------', $reflectionMethod->invokeArgs($cache, [str_repeat('-', 12)]));
        self::assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 12)])));
        self::assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 23)])));
        self::assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, [str_repeat('-', 40)])));
    }

    public function testTooLongNamespace()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Namespace must be 26 chars max, 40 given ("----------------------------------------")');
        self::getMockBuilder(MaxIdLengthAdapter::class)
            ->setConstructorArgs([str_repeat('-', 40)])
            ->getMock();
    }
}

abstract class MaxIdLengthAdapter extends AbstractAdapter
{
    protected $maxIdLength = 50;

    public function __construct(string $ns)
    {
        parent::__construct($ns);
    }
}
