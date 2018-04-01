<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Validator\Constraints\GroupSequence;

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
}
