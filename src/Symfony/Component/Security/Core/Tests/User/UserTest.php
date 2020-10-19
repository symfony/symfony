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
    public function testConstructorException()
    {
        $this->expectException('InvalidArgumentException');
        new User('', 'superpass');
    }

    public function testGetRoles()
    {
        $user = new User('fabien', 'superpass');
        $this->assertEquals([], $user->getRoles());

        $user = new User('fabien', 'superpass', ['ROLE_ADMIN']);
        $this->assertEquals(['ROLE_ADMIN'], $user->getRoles());
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

        $user = new User('fabien', 'superpass', [], true, false);
        $this->assertFalse($user->isAccountNonExpired());
    }

    public function testIsCredentialsNonExpired()
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isCredentialsNonExpired());

        $user = new User('fabien', 'superpass', [], true, true, false);
        $this->assertFalse($user->isCredentialsNonExpired());
    }

    public function testIsAccountNonLocked()
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isAccountNonLocked());

        $user = new User('fabien', 'superpass', [], true, true, true, false);
        $this->assertFalse($user->isAccountNonLocked());
    }

    public function testIsEnabled()
    {
        $user = new User('fabien', 'superpass');
        $this->assertTrue($user->isEnabled());

        $user = new User('fabien', 'superpass', [], false);
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
        return [
            [true, new User('username', 'password'), new User('username', 'password')],
            [false, new User('username', 'password', ['ROLE']), new User('username', 'password')],
            [false, new User('username', 'password', ['ROLE']), new User('username', 'password', ['NO ROLE'])],
            [false, new User('diff', 'diff'), new User('username', 'password')],
            [false, new User('diff', 'diff', [], false), new User('username', 'password')],
            [false, new User('diff', 'diff', [], false, false), new User('username', 'password')],
            [false, new User('diff', 'diff', [], false, false, false), new User('username', 'password')],
            [false, new User('diff', 'diff', [], false, false, false, false), new User('username', 'password')],
        ];
    }

    public function testIsEqualToWithDifferentUser()
    {
        $user = new User('username', 'password');
        $this->assertFalse($user->isEqualTo($this->getMockBuilder(UserInterface::class)->getMock()));
    }
}
