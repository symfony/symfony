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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @group legacy
 */
class UserTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testConstructorException()
    {
        self::expectException(\InvalidArgumentException::class);
        new User('', 'superpass');
    }

    public function testGetRoles()
    {
        $user = new User('fabien', 'superpass');
        self::assertEquals([], $user->getRoles());

        $user = new User('fabien', 'superpass', ['ROLE_ADMIN']);
        self::assertEquals(['ROLE_ADMIN'], $user->getRoles());
    }

    public function testGetPassword()
    {
        $user = new User('fabien', 'superpass');
        self::assertEquals('superpass', $user->getPassword());
    }

    /**
     * @group legacy
     */
    public function testGetUsername()
    {
        $user = new User('fabien', 'superpass');

        $this->expectDeprecation('Since symfony/security-core 5.3: Method "Symfony\Component\Security\Core\User\User::getUsername()" is deprecated, use getUserIdentifier() instead.');
        self::assertEquals('fabien', $user->getUsername());
    }

    public function testGetUserIdentifier()
    {
        $user = new User('fabien', 'superpass');
        self::assertEquals('fabien', $user->getUserIdentifier());
    }

    public function testGetSalt()
    {
        $user = new User('fabien', 'superpass');
        self::assertEquals('', $user->getSalt());
    }

    public function testIsAccountNonExpired()
    {
        $user = new User('fabien', 'superpass');
        self::assertTrue($user->isAccountNonExpired());

        $user = new User('fabien', 'superpass', [], true, false);
        self::assertFalse($user->isAccountNonExpired());
    }

    public function testIsCredentialsNonExpired()
    {
        $user = new User('fabien', 'superpass');
        self::assertTrue($user->isCredentialsNonExpired());

        $user = new User('fabien', 'superpass', [], true, true, false);
        self::assertFalse($user->isCredentialsNonExpired());
    }

    public function testIsAccountNonLocked()
    {
        $user = new User('fabien', 'superpass');
        self::assertTrue($user->isAccountNonLocked());

        $user = new User('fabien', 'superpass', [], true, true, true, false);
        self::assertFalse($user->isAccountNonLocked());
    }

    public function testIsEnabled()
    {
        $user = new User('fabien', 'superpass');
        self::assertTrue($user->isEnabled());

        $user = new User('fabien', 'superpass', [], false);
        self::assertFalse($user->isEnabled());
    }

    public function testEraseCredentials()
    {
        $user = new User('fabien', 'superpass');
        $user->eraseCredentials();
        self::assertEquals('superpass', $user->getPassword());
    }

    public function testToString()
    {
        $user = new User('fabien', 'superpass');
        self::assertEquals('fabien', (string) $user);
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
        self::assertSame($expectation, $a->isEqualTo($b));
        self::assertSame($expectation, $b->isEqualTo($a));
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
        self::assertFalse($user->isEqualTo(self::createMock(UserInterface::class)));
    }
}
