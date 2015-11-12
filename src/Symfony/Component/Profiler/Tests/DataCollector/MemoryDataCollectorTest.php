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

use Symfony\Component\Profiler\DataCollector\MemoryDataCollector;
use Symfony\Component\Profiler\ProfileData\MemoryData;

/**
 * MemoryDataCollectorTest.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class MemoryDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $collector = new MemoryDataCollector();

        $data = $collector->getCollectedData();

        $this->assertInstanceOf('Symfony\Component\Profiler\ProfileData\MemoryData', $data);
        $this->assertInternalType('integer', $data->getMemory());
        $this->assertInternalType('integer', $data->getMemoryLimit());
    }

    /** @dataProvider getBytesConversionTestData */
    public function testBytesConversion($limit, $bytes)
    {
        $data = new MemoryData(1, 1);
        $method = new \ReflectionMethod($data, 'convertToBytes');
        $method->setAccessible(true);
        $this->assertEquals($bytes, $method->invoke($data, $limit));
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
