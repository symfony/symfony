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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GroupSequenceTest extends TestCase
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

    /**
     * @group legacy
     */
    public function testLegacyIterate()
    {
        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        $this->assertSame(array('Group 1', 'Group 2'), iterator_to_array($sequence));
    }

    /**
     * @group legacy
     */
    public function testLegacyCount()
    {
        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        $this->assertCount(2, $sequence);
    }

    /**
     * @group legacy
     */
    public function testLegacyArrayAccess()
    {
        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        $this->assertSame('Group 1', $sequence[0]);
        $this->assertSame('Group 2', $sequence[1]);
        $this->assertArrayHasKey(0, $sequence);
        $this->assertArrayNotHasKey(2, $sequence);
        unset($sequence[0]);
        $this->assertArrayNotHasKey(0, $sequence);
        $sequence[] = 'Group 3';
        $this->assertArrayHasKey(2, $sequence);
        $this->assertSame('Group 3', $sequence[2]);
        $sequence[0] = 'Group 1';
        $this->assertArrayHasKey(0, $sequence);
        $this->assertSame('Group 1', $sequence[0]);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\OutOfBoundsException
     * @group legacy
     */
    public function testLegacyGetExpectsExistingKey()
    {
        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        $sequence[2];
    }

    /**
     * @group legacy
     */
    public function testLegacyUnsetIgnoresNonExistingKeys()
    {
        $sequence = new GroupSequence(array('Group 1', 'Group 2'));

        // should not fail
        unset($sequence[2]);

        $this->assertCount(2, $sequence);
    }
}
