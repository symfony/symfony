<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Role;

use Symfony\Component\Security\Role\SwitchUserRole;

class SwitchUserRoleTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSource()
    {
        $role = new SwitchUserRole('FOO', $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));

        $this->assertSame($token, $role->getSource());
    }

    public function testGetRole()
    {
        $role = new SwitchUserRole('FOO', $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));

        $this->assertEquals('FOO', $role->getRole());
    }
}
