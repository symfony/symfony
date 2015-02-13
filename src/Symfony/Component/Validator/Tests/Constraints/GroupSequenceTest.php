<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GroupSequenceTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        $this->assertSame(array('Group 1', 'Group 2'), $sequence->groups);
    }

    public function testCreateDoctrineStyle()
    {
        $sequence = new GroupSequence(array('value' => array('Group 1', 'Group 2')));

        $this->assertSame(array('Group 1', 'Group 2'), $sequence->groups);
    }

    public function testLegacyIterate()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        $this->assertSame(array('Group 1', 'Group 2'), iterator_to_array($sequence));
    }

    public function testLegacyCount()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        $this->assertCount(2, $sequence);
    }

    public function testLegacyArrayAccess()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        $this->assertSame('Group 1', $sequence[0]);
        $this->assertSame('Group 2', $sequence[1]);
        $this->assertTrue(isset($sequence[0]));
        $this->assertFalse(isset($sequence[2]));
        unset($sequence[0]);
        $this->assertFalse(isset($sequence[0]));
        $sequence[] = 'Group 3';
        $this->assertTrue(isset($sequence[2]));
        $this->assertSame('Group 3', $sequence[2]);
        $sequence[0] = 'Group 1';
        $this->assertTrue(isset($sequence[0]));
        $this->assertSame('Group 1', $sequence[0]);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\OutOfBoundsException
     */
    public function testLegacyGetExpectsExistingKey()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        $sequence[2];
    }

    public function testLegacyUnsetIgnoresNonExistingKeys()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        // should not fail
        unset($sequence[2]);
    }
}
