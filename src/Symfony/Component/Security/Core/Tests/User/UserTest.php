<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\User;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\User;

class UserTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorException(): void
    {
        new User('', 'superpass');
    }

    public function testGetRoles(): void
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals(array(), $user->getRoles());

        $user = new User('fabien', 'superpass', array('ROLE_ADMIN'));
        $this->assertEquals(array('ROLE_ADMIN'), $user->getRoles());
    }

    public function testGetPassword(): void
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals('superpass', $user->getPassword());
    }

    public function testGetUsername(): void
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals('fabien', $user->getUsername());
    }

    public function testGetSalt(): void
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals('', $user->getSalt());
    }

    public function testIsAccountNonExpired(): void
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isAccountNonExpired());

        $user = new User('fabien', 'superpass', array(), true, false);
        $this->assertFalse($user->isAccountNonExpired());
    }

    public function testIsCredentialsNonExpired(): void
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isCredentialsNonExpired());

        $user = new User('fabien', 'superpass', array(), true, true, false);
        $this->assertFalse($user->isCredentialsNonExpired());
    }

    public function testIsAccountNonLocked(): void
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isAccountNonLocked());

        $user = new User('fabien', 'superpass', array(), true, true, true, false);
        $this->assertFalse($user->isAccountNonLocked());
    }

    public function testIsEnabled(): void
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isEnabled());

        $user = new User('fabien', 'superpass', array(), false);
        $this->assertFalse($user->isEnabled());
    }

    public function testEraseCredentials(): void
    {
        $user = new User('fabien', 'superpass');
        $user->eraseCredentials();
        $this->assertEquals('superpass', $user->getPassword());
    }

    public function testToString(): void
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals('fabien', (string) $user);
    }
}
