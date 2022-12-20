<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\NativePasswordEncoder;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @group legacy
 */
class UserPasswordEncoderTest extends TestCase
{
    public function testEncodePassword()
    {
        $userMock = self::createMock(UserInterface::class);
        $userMock->expects(self::any())
            ->method('getSalt')
            ->willReturn('userSalt');

        $mockEncoder = self::createMock(PasswordEncoderInterface::class);
        $mockEncoder->expects(self::any())
            ->method('encodePassword')
            ->with(self::equalTo('plainPassword'), self::equalTo('userSalt'))
            ->willReturn('encodedPassword');

        $mockEncoderFactory = self::createMock(EncoderFactoryInterface::class);
        $mockEncoderFactory->expects(self::any())
            ->method('getEncoder')
            ->with(self::equalTo($userMock))
            ->willReturn($mockEncoder);

        $passwordEncoder = new UserPasswordEncoder($mockEncoderFactory);

        $encoded = $passwordEncoder->encodePassword($userMock, 'plainPassword');
        self::assertEquals('encodedPassword', $encoded);
    }

    public function testIsPasswordValid()
    {
        $userMock = self::createMock(UserInterface::class);
        $userMock->expects(self::any())
            ->method('getSalt')
            ->willReturn('userSalt');
        $userMock->expects(self::any())
            ->method('getPassword')
            ->willReturn('encodedPassword');

        $mockEncoder = self::createMock(PasswordEncoderInterface::class);
        $mockEncoder->expects(self::any())
            ->method('isPasswordValid')
            ->with(self::equalTo('encodedPassword'), self::equalTo('plainPassword'), self::equalTo('userSalt'))
            ->willReturn(true);

        $mockEncoderFactory = self::createMock(EncoderFactoryInterface::class);
        $mockEncoderFactory->expects(self::any())
            ->method('getEncoder')
            ->with(self::equalTo($userMock))
            ->willReturn($mockEncoder);

        $passwordEncoder = new UserPasswordEncoder($mockEncoderFactory);

        $isValid = $passwordEncoder->isPasswordValid($userMock, 'plainPassword');
        self::assertTrue($isValid);
    }

    public function testNeedsRehash()
    {
        $user = new User('username', null);
        $encoder = new NativePasswordEncoder(4, 20000, 4);

        $mockEncoderFactory = self::createMock(EncoderFactoryInterface::class);
        $mockEncoderFactory->expects(self::any())
            ->method('getEncoder')
            ->with($user)
            ->will(self::onConsecutiveCalls($encoder, $encoder, new NativePasswordEncoder(5, 20000, 5), $encoder));

        $passwordEncoder = new UserPasswordEncoder($mockEncoderFactory);

        $user->setPassword($passwordEncoder->encodePassword($user, 'foo', 'salt'));
        self::assertFalse($passwordEncoder->needsRehash($user));
        self::assertTrue($passwordEncoder->needsRehash($user));
        self::assertFalse($passwordEncoder->needsRehash($user));
    }
}
