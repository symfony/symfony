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
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class UserTest extends TestCase
{
    public function testConstructorException()
    {
        $this->expectException(\InvalidArgumentException::class);
        new InMemoryUser('', 'superpass');
    }

    public function testGetRoles()
    {
        $user = new InMemoryUser('fabien', 'superpass');
        $this->assertEquals([], $user->getRoles());

        $user = new InMemoryUser('fabien', 'superpass', ['ROLE_ADMIN']);
        $this->assertEquals(['ROLE_ADMIN'], $user->getRoles());
    }

    public function testGetPassword()
    {
        $user = new InMemoryUser('fabien', 'superpass');
        $this->assertEquals('superpass', $user->getPassword());
    }

    public function testGetUsername()
    {
        $user = new InMemoryUser('fabien', 'superpass');
        $this->assertEquals('fabien', $user->getUsername());
    }

    public function testGetSalt()
    {
        $user = new InMemoryUser('fabien', 'superpass');
        $this->assertEquals('', $user->getSalt());
    }

    public function testEraseCredentials()
    {
        $user = new InMemoryUser('fabien', 'superpass');
        $user->eraseCredentials();
        $this->assertEquals('superpass', $user->getPassword());
    }

    public function testToString()
    {
        $user = new InMemoryUser('fabien', 'superpass');
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
            [true, new InMemoryUser('username', 'password'), new InMemoryUser('username', 'password')],
            [false, new InMemoryUser('username', 'password', ['ROLE']), new InMemoryUser('username', 'password')],
            [false, new InMemoryUser('username', 'password', ['ROLE']), new InMemoryUser('username', 'password', ['NO ROLE'])],
            [false, new InMemoryUser('diff', 'diff'), new InMemoryUser('username', 'password')],
            [false, new InMemoryUser('diff', 'diff', [], []), new InMemoryUser('username', 'password')],
        ];
    }

    public function testIsEqualToWithDifferentUser()
    {
        $user = new InMemoryUser('username', 'password');
        $this->assertFalse($user->isEqualTo($this->createMock(UserInterface::class)));
    }
}
