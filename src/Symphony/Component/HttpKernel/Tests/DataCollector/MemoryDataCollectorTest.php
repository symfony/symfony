<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;

class MemoryDataCollectorTest extends TestCase
{
    public function testCollect()
    {
        $collector = new MemoryDataCollector();
        $collector->collect(new Request(), new Response());

        $this->assertInternalType('integer', $collector->getMemory());
        $this->assertInternalType('integer', $collector->getMemoryLimit());
        $this->assertSame('memory', $collector->getName());
    }

    /** @dataProvider getBytesConversionTestData */
    public function testBytesConversion($limit, $bytes)
    {
        $collector = new MemoryDataCollector();
        $method = new \ReflectionMethod($collector, 'convertToBytes');
        $method->setAccessible(true);
        $this->assertEquals($bytes, $method->invoke($collector, $limit));
    }

    public function getBytesConversionTestData()
    {
        return array(
            array('2k', 2048),
            array('2 k', 2048),
            array('8m', 8 * 1024 * 1024),
            array('+2 k', 2048),
            array('+2???k', 2048),
            array('0x10', 16),
            array('0xf', 15),
            array('010', 8),
            array('+0x10 k', 16 * 1024),
            array('1g', 1024 * 1024 * 1024),
            array('1G', 1024 * 1024 * 1024),
            array('-1', -1),
            array('0', 0),
            array('2mk', 2048), // the unit must be the last char, so in this case 'k', not 'm'
        );
    }
}
