<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Util;

class ServerParamsTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getGetPostMaxSizeTestData */
    public function testGetPostMaxSize($size, $bytes)
    {
        $serverParams = $this->getMock('Symfony\Component\Form\Extension\Validator\Util\ServerParams', array('getNormalizedIniPostMaxSize'));
        $serverParams
            ->expects($this->any())
            ->method('getNormalizedIniPostMaxSize')
            ->will($this->returnValue(strtoupper($size)));

        $this->assertEquals($bytes, $serverParams->getPostMaxSize());
    }

    public function getGetPostMaxSizeTestData()
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
            array('-1', -1),
            array('0', 0),
            array('2mk', 2048), // the unit must be the last char, so in this case 'k', not 'm'
        );
    }
}
