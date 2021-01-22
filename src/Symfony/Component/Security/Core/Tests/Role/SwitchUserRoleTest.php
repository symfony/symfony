<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Role;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Role\SwitchUserRole;

/**
 * @group legacy
 */
class SwitchUserRoleTest extends TestCase
{
    public function testGetSource()
    {
        $role = new SwitchUserRole('FOO', $token = $this->createMock(TokenInterface::class));

        $this->assertSame($token, $role->getSource());
    }

    public function testGetRole()
    {
        $role = new SwitchUserRole('FOO', $this->createMock(TokenInterface::class));

        $this->assertEquals('FOO', $role->getRole());
    }
}
