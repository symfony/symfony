<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition\Builder;

use Symfony\Component\Config\Definition\Builder\DateTimeNodeDefinition;
use Symfony\Component\Config\Definition\DateTimeNode;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class DateTimeNodeDefinitionTest extends \PHPUnit_Framework_TestCase
{
    public function testGetNode()
    {
        $def = new DateTimeNodeDefinition('date');

        $this->assertInstanceOf(DateTimeNode::class, $def->getNode());
    }

    public function testFormat()
    {
        $def = new DateTimeNodeDefinition('date');

        $this->assertSame($def, $def->format('d/m/Y'));
        $this->assertSame('d/m/Y', $def->getNode()->getFormat());
    }

    public function testTimeZone()
    {
        $def = new DateTimeNodeDefinition('date');

        $this->assertSame($def, $def->timezone($timezone = new \DateTimeZone('Japan')));
        $this->assertSame($timezone, $def->getNode()->getTimeZone());
    }

    public function testTimeZoneIdentifier()
    {
        $def = new DateTimeNodeDefinition('date');

        $this->assertSame($def, $def->timezone('Japan'));
        $this->assertEquals(new \DateTimeZone('Japan'), $def->getNode()->getTimeZone());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage ->timezone() must be called with a valid timezone identifier or a "\DateTimeZone" instance.
     */
    public function testInvalidTimeZone()
    {
        $def = new DateTimeNodeDefinition('date');

        $def->timezone($timezone = 'invalid_timezone');
    }
}
