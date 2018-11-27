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
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class UserTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorException()
    {
        new User('', 'superpass');
    }

    public function testGetRoles()
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals(array(), $user->getRoles());

        $user = new User('fabien', 'superpass', array('ROLE_ADMIN'));
        $this->assertEquals(array('ROLE_ADMIN'), $user->getRoles());
    }

    public function testGetPassword()
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals('superpass', $user->getPassword());
    }

    public function testGetUsername()
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals('fabien', $user->getUsername());
    }

    public function testGetSalt()
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals('', $user->getSalt());
    }

    public function testIsAccountNonExpired()
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isAccountNonExpired());

        $user = new User('fabien', 'superpass', array(), true, false);
        $this->assertFalse($user->isAccountNonExpired());
    }

    public function testIsCredentialsNonExpired()
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isCredentialsNonExpired());

        $user = new User('fabien', 'superpass', array(), true, true, false);
        $this->assertFalse($user->isCredentialsNonExpired());
    }

    public function testIsAccountNonLocked()
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isAccountNonLocked());

        $user = new User('fabien', 'superpass', array(), true, true, true, false);
        $this->assertFalse($user->isAccountNonLocked());
    }

    public function testIsEnabled()
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isEnabled());

        $user = new User('fabien', 'superpass', array(), false);
        $this->assertFalse($user->isEnabled());
    }

    public function testEraseCredentials()
    {
        $user = new User('fabien', 'superpass');
        $user->eraseCredentials();
        $this->assertEquals('superpass', $user->getPassword());
    }

    public function testToString()
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals('fabien', (string) $user);
    }

    /**
     * @dataProvider isEqualToData
     *
     * @param bool                             $expectation
     * @param EquatableInterface|UserInterface $a
     * @param EquatableInterface|UserInterface $b
     */
    public function testIsEqualTo($expectation, $a, $b)
    {
        $this->assertSame($expectation, $a->isEqualTo($b));
        $this->assertSame($expectation, $b->isEqualTo($a));
    }

    public static function isEqualToData()
    {
        return array(
            array(true, new User('username', 'password'), new User('username', 'password')),
            array(true, new User('username', 'password', array('ROLE')), new User('username', 'password')),
            array(true, new User('username', 'password', array('ROLE')), new User('username', 'password', array('NO ROLE'))),
            array(false, new User('diff', 'diff'), new User('username', 'password')),
            array(false, new User('diff', 'diff', array(), false), new User('username', 'password')),
            array(false, new User('diff', 'diff', array(), false, false), new User('username', 'password')),
            array(false, new User('diff', 'diff', array(), false, false, false), new User('username', 'password')),
            array(false, new User('diff', 'diff', array(), false, false, false, false), new User('username', 'password')),
        );
    }

    public function testIsEqualToWithDifferentUser()
    {
        $user = new User('username', 'password');
        $this->assertFalse($user->isEqualTo($this->getMockBuilder(UserInterface::class)->getMock()));
    }
}
