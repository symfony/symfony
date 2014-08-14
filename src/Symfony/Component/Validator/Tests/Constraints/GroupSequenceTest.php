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
    public function testCreateDoctrineStyle()
    {
        $sequence = new GroupSequence(array('value' => array('Group 1', 'Group 2')));

        $this->assertSame(array('Group 1', 'Group 2'), $sequence->groups);
    }
}
