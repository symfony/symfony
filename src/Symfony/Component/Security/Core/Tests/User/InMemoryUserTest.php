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
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class InMemoryUserTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testConstructorException()
    {
        self::expectException(\InvalidArgumentException::class);
        new InMemoryUser('', 'superpass');
    }

    public function testGetRoles()
    {
        $user = new InMemoryUser('fabien', 'superpass');
        self::assertEquals([], $user->getRoles());

        $user = new InMemoryUser('fabien', 'superpass', ['ROLE_ADMIN']);
        self::assertEquals(['ROLE_ADMIN'], $user->getRoles());
    }

    public function testGetPassword()
    {
        $user = new InMemoryUser('fabien', 'superpass');
        self::assertEquals('superpass', $user->getPassword());
    }

    /**
     * @group legacy
     */
    public function testGetUsername()
    {
        $user = new InMemoryUser('fabien', 'superpass');

        $this->expectDeprecation('Since symfony/security-core 5.3: Method "Symfony\Component\Security\Core\User\User::getUsername()" is deprecated, use getUserIdentifier() instead.');
        self::assertEquals('fabien', $user->getUsername());
    }

    public function testGetUserIdentifier()
    {
        $user = new InMemoryUser('fabien', 'superpass');
        self::assertEquals('fabien', $user->getUserIdentifier());
    }

    public function testGetSalt()
    {
        $user = new InMemoryUser('fabien', 'superpass');
        self::assertNull($user->getSalt());
    }

    public function testIsEnabled()
    {
        $user = new InMemoryUser('mathilde', 'k');
        self::assertTrue($user->isEnabled());

        $user = new InMemoryUser('robin', 'superpass', [], false);
        self::assertFalse($user->isEnabled());
    }

    public function testEraseCredentials()
    {
        $user = new InMemoryUser('fabien', 'superpass');
        $user->eraseCredentials();
        self::assertEquals('superpass', $user->getPassword());
    }

    public function testToString()
    {
        $user = new InMemoryUser('fabien', 'superpass');
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
            [true, new InMemoryUser('username', 'password'), new InMemoryUser('username', 'password')],
            [false, new InMemoryUser('username', 'password', ['ROLE']), new InMemoryUser('username', 'password')],
            [false, new InMemoryUser('username', 'password', ['ROLE']), new InMemoryUser('username', 'password', ['NO ROLE'])],
            [false, new InMemoryUser('diff', 'diff'), new InMemoryUser('username', 'password')],
            [false, new InMemoryUser('diff', 'diff', [], false), new InMemoryUser('username', 'password')],
        ];
    }

    public function testIsEqualToWithDifferentUser()
    {
        $user = new InMemoryUser('username', 'password');
        self::assertFalse($user->isEqualTo(self::createMock(UserInterface::class)));
    }
}
