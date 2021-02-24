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
        $userMock = $this->createMock(UserInterface::class);
        $userMock->expects($this->any())
            ->method('getSalt')
            ->willReturn('userSalt');

        $mockEncoder = $this->createMock(PasswordEncoderInterface::class);
        $mockEncoder->expects($this->any())
            ->method('encodePassword')
            ->with($this->equalTo('plainPassword'), $this->equalTo('userSalt'))
            ->willReturn('encodedPassword');

        $mockEncoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $mockEncoderFactory->expects($this->any())
            ->method('getEncoder')
            ->with($this->equalTo($userMock))
            ->willReturn($mockEncoder);

        $passwordEncoder = new UserPasswordEncoder($mockEncoderFactory);

        $encoded = $passwordEncoder->encodePassword($userMock, 'plainPassword');
        $this->assertEquals('encodedPassword', $encoded);
    }

    public function testIsPasswordValid()
    {
        $userMock = $this->createMock(UserInterface::class);
        $userMock->expects($this->any())
            ->method('getSalt')
            ->willReturn('userSalt');
        $userMock->expects($this->any())
            ->method('getPassword')
            ->willReturn('encodedPassword');

        $mockEncoder = $this->createMock(PasswordEncoderInterface::class);
        $mockEncoder->expects($this->any())
            ->method('isPasswordValid')
            ->with($this->equalTo('encodedPassword'), $this->equalTo('plainPassword'), $this->equalTo('userSalt'))
            ->willReturn(true);

        $mockEncoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $mockEncoderFactory->expects($this->any())
            ->method('getEncoder')
            ->with($this->equalTo($userMock))
            ->willReturn($mockEncoder);

        $passwordEncoder = new UserPasswordEncoder($mockEncoderFactory);

        $isValid = $passwordEncoder->isPasswordValid($userMock, 'plainPassword');
        $this->assertTrue($isValid);
    }

    public function testNeedsRehash()
    {
        $user = new User('username', null);
        $encoder = new NativePasswordEncoder(4, 20000, 4);

        $mockEncoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $mockEncoderFactory->expects($this->any())
            ->method('getEncoder')
            ->with($user)
            ->will($this->onConsecutiveCalls($encoder, $encoder, new NativePasswordEncoder(5, 20000, 5), $encoder));

        $passwordEncoder = new UserPasswordEncoder($mockEncoderFactory);

        $user->setPassword($passwordEncoder->encodePassword($user, 'foo', 'salt'));
        $this->assertFalse($passwordEncoder->needsRehash($user));
        $this->assertTrue($passwordEncoder->needsRehash($user));
        $this->assertFalse($passwordEncoder->needsRehash($user));
    }
}
