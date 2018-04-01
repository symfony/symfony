<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Role;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Role\SwitchUserRole;

class SwitchUserRoleTest extends TestCase
{
    public function testGetSource()
    {
        $role = new SwitchUserRole('FOO', $token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());

        $this->assertSame($token, $role->getSource());
    }

    public function testGetRole()
    {
        $role = new SwitchUserRole('FOO', $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());

        $this->assertEquals('FOO', $role->getRole());
    }
}
