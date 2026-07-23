<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Tests\Hasher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\Tests\Fixtures\TestLegacyPasswordAuthenticatedUser;
use Symfony\Component\PasswordHasher\Tests\Fixtures\TestPasswordAuthenticatedUser;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UserPasswordHasherTest extends TestCase
{
    public function testHashWithLegacyUser()
    {
        $user = new TestLegacyPasswordAuthenticatedUser('name', null, 'userSalt');

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher
            ->expects($this->once())
            ->method('hash')
            ->with($this->equalTo('plainPassword'), $this->equalTo('userSalt'))
            ->willReturn('hash');

        $passwordHasherFactory = new PasswordHasherFactory([
            $user::class => $passwordHasher,
        ]);

        $passwordHasher = new UserPasswordHasher($passwordHasherFactory);

        $encoded = $passwordHasher->hashPassword($user, 'plainPassword');
        $this->assertEquals('hash', $encoded);
    }

    public function testHashWithPasswordAuthenticatedUser()
    {
        $user = new TestPasswordAuthenticatedUser();

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher
            ->expects($this->once())
            ->method('hash')
            ->with($this->equalTo('plainPassword'), $this->equalTo(null))
            ->willReturn('hash');

        $passwordHasherFactory = new PasswordHasherFactory([
            $user::class => $passwordHasher,
        ]);

        $passwordHasher = new UserPasswordHasher($passwordHasherFactory);

        $hashedPassword = $passwordHasher->hashPassword($user, 'plainPassword');

        $this->assertSame('hash', $hashedPassword);
    }

    public function testVerifyWithLegacyUser()
    {
        $user = new TestLegacyPasswordAuthenticatedUser('user', 'hash', 'userSalt');

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher
            ->expects($this->once())
            ->method('verify')
            ->with($this->equalTo('hash'), $this->equalTo('plainPassword'), $this->equalTo('userSalt'))
            ->willReturn(true);

        $passwordHasherFactory = new PasswordHasherFactory([
            $user::class => $passwordHasher,
        ]);

        $passwordHasher = new UserPasswordHasher($passwordHasherFactory);

        $isValid = $passwordHasher->isPasswordValid($user, 'plainPassword');
        $this->assertTrue($isValid);
    }

    public function testVerify()
    {
        $user = new TestPasswordAuthenticatedUser('hash');

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher
            ->expects($this->once())
            ->method('verify')
            ->with($this->equalTo('hash'), $this->equalTo('plainPassword'), $this->equalTo(null))
            ->willReturn(true);

        $passwordHasherFactory = new PasswordHasherFactory([
            $user::class => $passwordHasher,
        ]);

        $passwordHasher = new UserPasswordHasher($passwordHasherFactory);

        $isValid = $passwordHasher->isPasswordValid($user, 'plainPassword');
        $this->assertTrue($isValid);
    }

    public function testNeedsRehash()
    {
        $user = new InMemoryUser('username', null);
        $hasher = new NativePasswordHasher(4, 20000, 4);

        $passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $passwordHasherFactory
            ->expects($this->exactly(4))
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($hasher, $hasher, new NativePasswordHasher(5, 20000, 5), $hasher);

        $passwordHasher = new UserPasswordHasher($passwordHasherFactory);

        \Closure::bind(function () use ($passwordHasher) { $this->password = $passwordHasher->hashPassword($this, 'foo', 'salt'); }, $user, InMemoryUser::class)();
        $this->assertFalse($passwordHasher->needsRehash($user));
        $this->assertTrue($passwordHasher->needsRehash($user));
        $this->assertFalse($passwordHasher->needsRehash($user));
    }
}
