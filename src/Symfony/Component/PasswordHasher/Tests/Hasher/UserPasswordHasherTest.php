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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
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
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testHashWithNonPasswordAuthenticatedUser()
    {
        $this->expectDeprecation('Since symfony/password-hasher 5.3: Returning a string from "getSalt()" without implementing the "Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface" interface is deprecated, the "%s" class should implement it.');

        $userMock = self::createMock('Symfony\Component\Security\Core\User\UserInterface');
        $userMock->expects(self::any())
            ->method('getSalt')
            ->willReturn('userSalt');

        $mockHasher = self::createMock(PasswordHasherInterface::class);
        $mockHasher->expects(self::any())
            ->method('hash')
            ->with(self::equalTo('plainPassword'), self::equalTo('userSalt'))
            ->willReturn('hash');

        $mockPasswordHasherFactory = self::createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects(self::any())
            ->method('getPasswordHasher')
            ->with(self::equalTo($userMock))
            ->willReturn($mockHasher);

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        $encoded = $passwordHasher->hashPassword($userMock, 'plainPassword');
        self::assertEquals('hash', $encoded);
    }

    public function testHashWithLegacyUser()
    {
        $user = new TestLegacyPasswordAuthenticatedUser('name', null, 'userSalt');

        $mockHasher = self::createMock(PasswordHasherInterface::class);
        $mockHasher->expects(self::any())
            ->method('hash')
            ->with(self::equalTo('plainPassword'), self::equalTo('userSalt'))
            ->willReturn('hash');

        $mockPasswordHasherFactory = self::createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects(self::any())
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($mockHasher);

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        $encoded = $passwordHasher->hashPassword($user, 'plainPassword');
        self::assertEquals('hash', $encoded);
    }

    public function testHashWithPasswordAuthenticatedUser()
    {
        $user = new TestPasswordAuthenticatedUser();

        $mockHasher = self::createMock(PasswordHasherInterface::class);
        $mockHasher->expects(self::any())
            ->method('hash')
            ->with(self::equalTo('plainPassword'), self::equalTo(null))
            ->willReturn('hash');

        $mockPasswordHasherFactory = self::createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects(self::any())
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($mockHasher);

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        $hashedPassword = $passwordHasher->hashPassword($user, 'plainPassword');

        self::assertSame('hash', $hashedPassword);
    }

    public function testVerifyWithLegacyUser()
    {
        $user = new TestLegacyPasswordAuthenticatedUser('user', 'hash', 'userSalt');

        $mockHasher = self::createMock(PasswordHasherInterface::class);
        $mockHasher->expects(self::any())
            ->method('verify')
            ->with(self::equalTo('hash'), self::equalTo('plainPassword'), self::equalTo('userSalt'))
            ->willReturn(true);

        $mockPasswordHasherFactory = self::createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects(self::any())
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($mockHasher);

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        $isValid = $passwordHasher->isPasswordValid($user, 'plainPassword');
        self::assertTrue($isValid);
    }

    public function testVerify()
    {
        $user = new TestPasswordAuthenticatedUser('hash');

        $mockHasher = self::createMock(PasswordHasherInterface::class);
        $mockHasher->expects(self::any())
            ->method('verify')
            ->with(self::equalTo('hash'), self::equalTo('plainPassword'), self::equalTo(null))
            ->willReturn(true);

        $mockPasswordHasherFactory = self::createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects(self::any())
            ->method('getPasswordHasher')
            ->with($user)
            ->willReturn($mockHasher);

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        $isValid = $passwordHasher->isPasswordValid($user, 'plainPassword');
        self::assertTrue($isValid);
    }

    public function testNeedsRehash()
    {
        $user = new InMemoryUser('username', null);
        $hasher = new NativePasswordHasher(4, 20000, 4);

        $mockPasswordHasherFactory = self::createMock(PasswordHasherFactoryInterface::class);
        $mockPasswordHasherFactory->expects(self::any())
            ->method('getPasswordHasher')
            ->with($user)
            ->will(self::onConsecutiveCalls($hasher, $hasher, new NativePasswordHasher(5, 20000, 5), $hasher));

        $passwordHasher = new UserPasswordHasher($mockPasswordHasherFactory);

        \Closure::bind(function () use ($passwordHasher) { $this->password = $passwordHasher->hashPassword($this, 'foo', 'salt'); }, $user, class_exists(User::class) ? User::class : InMemoryUser::class)();
        self::assertFalse($passwordHasher->needsRehash($user));
        self::assertTrue($passwordHasher->needsRehash($user));
        self::assertFalse($passwordHasher->needsRehash($user));
    }
}
