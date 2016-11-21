<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use Symfony\Component\Config\Definition\DateTimeNode;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class DateTimeNodeTest extends \PHPUnit_Framework_TestCase
{
    public function getValidValues()
    {
        return array(
            array('now'),
            array('-2 days'),
            array('04/18/1992'),
            array('2016-11-21T19:18:54+0100'),
            array(1479751616),
        );
    }

    /**
     * @dataProvider getValidValues
     */
    public function testFinalizeValue($value)
    {
        $node = new DateTimeNode('date');

        $this->assertInstanceOf(\DateTime::class, $node->finalize($value));
    }

    public function testFinalizeNull()
    {
        $node = new DateTimeNode('date');

        $this->assertNull($node->finalize(null));
    }

    public function testFinalizeValueFromTimestamp()
    {
        $node = new DateTimeNode('date');

        $this->assertEquals(new \DateTime('@1479751616'), $node->finalize(1479751616));
    }

    public function testFinalizeValueFromString()
    {
        $node = new DateTimeNode('date');

        $this->assertEquals(new \DateTime('2016-11-21T19:18:54+0100'), $node->finalize('2016-11-21T19:18:54+0100'));
    }

    public function testFinalizeValueFromFormat()
    {
        $node = new DateTimeNode('date', null, $format = 'd/m/Y');

        $this->assertEquals(\DateTime::createFromFormat($format, '18/04/1992'), $node->finalize('18/04/1992'));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid value for path "date". Unable to parse datetime string "04/18/1992" according to specified "d/m/Y" format.
     */
    public function testFinalizeValueFromFormatMismatch()
    {
        $node = new DateTimeNode('date', null, $format = 'd/m/Y');
        $node->finalize('04/18/1992');
    }

    public function testFinalizeValueWithTimezone()
    {
        $node = new DateTimeNode('date', null, null, new \DateTimeZone('Japan'));

        $this->assertEquals(new \DateTimeZone('Japan'), $node->finalize('now')->getTimezone());
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidTypeException
     * @expectedExceptionMessage Invalid type for path "date". Expected int, string or empty, but got double.
     */
    public function testFinalizeWithInvalidType()
    {
        $node = new DateTimeNode('date');
        $node->finalize(2.3);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid value for path "date". Unable to interpret datetime string "invalid_date" as a datetime. Please provide a "strtotime" understandable datetime string.
     */
    public function testFinalizeUninterpretableInvalidDateTime()
    {
        $node = new DateTimeNode('date');
        $node->finalize('invalid_date');
    }
}
