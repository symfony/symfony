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
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\Tests\Fixtures\TestLegacyPasswordAuthenticatedUser;
use Symfony\Component\PasswordHasher\Tests\Fixtures\TestPasswordAuthenticatedUser;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\User;

class UserPasswordHasherTest extends TestCase
{
    public function testHashWithLegacyUser()
    {
        $user = new TestLegacyPasswordAuthenticatedUser('name', null, 'userSalt');

        $mockHasher = $this->createMock(PasswordHasherInterface::class);
        $mockHasher->expects($this->any())
            ->method('hash')
            ->with($this->equalTo('plainPassword'), $this->equalTo('userSalt'))
            ->willReturn('hash');

        $mockPasswordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects($this->any())
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($mockHasher);

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        $encoded = $passwordHasher->hashPassword($user, 'plainPassword');
        $this->assertEquals('hash', $encoded);
    }

    public function testHashWithPasswordAuthenticatedUser()
    {
        $user = new TestPasswordAuthenticatedUser();

        $mockHasher = $this->createMock(PasswordHasherInterface::class);
        $mockHasher->expects($this->any())
            ->method('hash')
            ->with($this->equalTo('plainPassword'), $this->equalTo(null))
            ->willReturn('hash');

        $mockPasswordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects($this->any())
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($mockHasher);

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        $hashedPassword = $passwordHasher->hashPassword($user, 'plainPassword');

        $this->assertSame('hash', $hashedPassword);
    }

    public function testVerifyWithLegacyUser()
    {
        $user = new TestLegacyPasswordAuthenticatedUser('user', 'hash', 'userSalt');

        $mockHasher = $this->createMock(PasswordHasherInterface::class);
        $mockHasher->expects($this->any())
            ->method('verify')
            ->with($this->equalTo('hash'), $this->equalTo('plainPassword'), $this->equalTo('userSalt'))
            ->willReturn(true);

        $mockPasswordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects($this->any())
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($mockHasher);

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        $isValid = $passwordHasher->isPasswordValid($user, 'plainPassword');
        $this->assertTrue($isValid);
    }

    public function testVerify()
    {
        $user = new TestPasswordAuthenticatedUser('hash');

        $mockHasher = $this->createMock(PasswordHasherInterface::class);
        $mockHasher->expects($this->any())
            ->method('verify')
            ->with($this->equalTo('hash'), $this->equalTo('plainPassword'), $this->equalTo(null))
            ->willReturn(true);

        $mockPasswordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects($this->any())
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($mockHasher);

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        $isValid = $passwordHasher->isPasswordValid($user, 'plainPassword');
        $this->assertTrue($isValid);
    }

    public function testNeedsRehash()
    {
        $user = new InMemoryUser('username', null);
        $hasher = new NativePasswordHasher(4, 20000, 4);

        $mockPasswordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects($this->any())
            ->method('getPasswordHasher')
            ->with($user)
            ->will($this->onConsecutiveCalls($hasher, $hasher, new NativePasswordHasher(5, 20000, 5), $hasher));

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        \Closure::bind(function () use ($passwordHasher) { $this->password = $passwordHasher->hashPassword($this, 'foo', 'salt'); }, $user, class_exists(User::class) ? User::class : InMemoryUser::class)();
        $this->assertFalse($passwordHasher->needsRehash($user));
        $this->assertTrue($passwordHasher->needsRehash($user));
        $this->assertFalse($passwordHasher->needsRehash($user));
    }
}
