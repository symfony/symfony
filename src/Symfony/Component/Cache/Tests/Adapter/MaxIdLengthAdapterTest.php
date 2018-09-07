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

class MaxIdLengthAdapterTest extends TestCase
{
    public function testLongKey()
    {
        $cache = $this->getMockBuilder(MaxIdLengthAdapter::class)
            ->setConstructorArgs(array(str_repeat('-', 10)))
            ->setMethods(array('doHave', 'doFetch', 'doDelete', 'doSave', 'doClear'))
            ->getMock();

        $cache->expects($this->exactly(2))
            ->method('doHave')
            ->withConsecutive(
                array($this->equalTo('----------:nWfzGiCgLczv3SSUzXL3kg:')),
                array($this->equalTo('----------:---------------------------------------'))
            );

        $cache->hasItem(str_repeat('-', 40));
        $cache->hasItem(str_repeat('-', 39));
    }

    public function testLongKeyVersioning()
    {
        $cache = $this->getMockBuilder(MaxIdLengthAdapter::class)
            ->setConstructorArgs(array(str_repeat('-', 26)))
            ->getMock();

        $reflectionClass = new \ReflectionClass(AbstractAdapter::class);

        $reflectionMethod = $reflectionClass->getMethod('getId');
        $reflectionMethod->setAccessible(true);

        // No versioning enabled
        $this->assertEquals('--------------------------:------------', $reflectionMethod->invokeArgs($cache, array(str_repeat('-', 12))));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, array(str_repeat('-', 12)))));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, array(str_repeat('-', 23)))));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, array(str_repeat('-', 40)))));

        $reflectionProperty = $reflectionClass->getProperty('versioningIsEnabled');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($cache, true);

        // Versioning enabled
        $this->assertEquals('--------------------------:1/------------', $reflectionMethod->invokeArgs($cache, array(str_repeat('-', 12))));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, array(str_repeat('-', 12)))));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, array(str_repeat('-', 23)))));
        $this->assertLessThanOrEqual(50, \strlen($reflectionMethod->invokeArgs($cache, array(str_repeat('-', 40)))));
    }

    /**
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage Namespace must be 26 chars max, 40 given ("----------------------------------------")
     */
    public function testTooLongNamespace()
    {
        $cache = $this->getMockBuilder(MaxIdLengthAdapter::class)
            ->setConstructorArgs(array(str_repeat('-', 40)))
            ->getMock();
    }
}

abstract class MaxIdLengthAdapter extends AbstractAdapter
{
    protected $maxIdLength = 50;

    public function __construct($ns)
    {
        parent::__construct($ns);
    }
}
