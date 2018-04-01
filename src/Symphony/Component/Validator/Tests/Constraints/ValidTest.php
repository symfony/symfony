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
use Symphony\Component\Validator\Constraints\Valid;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidTest extends TestCase
{
    public function testGroupsCanBeSet()
    {
        $constraint = new Valid(array('groups' => 'foo'));

        $this->assertSame(array('foo'), $constraint->groups);
    }

    public function testGroupsAreNullByDefault()
    {
        $constraint = new Valid();

        $this->assertNull($constraint->groups);
    }
}
