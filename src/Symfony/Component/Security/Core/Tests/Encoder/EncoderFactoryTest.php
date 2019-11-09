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
use Symfony\Component\Security\Core\Encoder\EncoderAwareInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\MigratingPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\NativePasswordEncoder;
use Symfony\Component\Security\Core\Encoder\SodiumPasswordEncoder;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class EncoderFactoryTest extends TestCase
{
    public function testGetEncoderWithMessageDigestEncoder()
    {
        $factory = new EncoderFactory(['Symfony\Component\Security\Core\User\UserInterface' => [
            'class' => 'Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder',
            'arguments' => ['sha512', true, 5],
        ]]);

        $encoder = $factory->getEncoder($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock());
        $expectedEncoder = new MessageDigestPasswordEncoder('sha512', true, 5);

        $this->assertEquals($expectedEncoder->encodePassword('foo', 'moo'), $encoder->encodePassword('foo', 'moo'));
    }

    public function testGetEncoderWithService()
    {
        $factory = new EncoderFactory([
            'Symfony\Component\Security\Core\User\UserInterface' => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock());
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));

        $encoder = $factory->getEncoder(new User('user', 'pass'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetEncoderWithClassName()
    {
        $factory = new EncoderFactory([
            'Symfony\Component\Security\Core\User\UserInterface' => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder('Symfony\Component\Security\Core\Tests\Encoder\SomeChildUser');
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetEncoderConfiguredForConcreteClassWithService()
    {
        $factory = new EncoderFactory([
            'Symfony\Component\Security\Core\User\User' => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder(new User('user', 'pass'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetEncoderConfiguredForConcreteClassWithClassName()
    {
        $factory = new EncoderFactory([
            'Symfony\Component\Security\Core\Tests\Encoder\SomeUser' => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder('Symfony\Component\Security\Core\Tests\Encoder\SomeChildUser');
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetNamedEncoderForEncoderAware()
    {
        $factory = new EncoderFactory([
            'Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser' => new MessageDigestPasswordEncoder('sha256'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder(new EncAwareUser('user', 'pass'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetNullNamedEncoderForEncoderAware()
    {
        $factory = new EncoderFactory([
            'Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser' => new MessageDigestPasswordEncoder('sha1'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha256'),
        ]);

        $user = new EncAwareUser('user', 'pass');
        $user->encoderName = null;
        $encoder = $factory->getEncoder($user);
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetInvalidNamedEncoderForEncoderAware()
    {
        $this->expectException('RuntimeException');
        $factory = new EncoderFactory([
            'Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser' => new MessageDigestPasswordEncoder('sha1'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha256'),
        ]);

        $user = new EncAwareUser('user', 'pass');
        $user->encoderName = 'invalid_encoder_name';
        $factory->getEncoder($user);
    }

    public function testGetEncoderForEncoderAwareWithClassName()
    {
        $factory = new EncoderFactory([
            'Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser' => new MessageDigestPasswordEncoder('sha1'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha256'),
        ]);

        $encoder = $factory->getEncoder('Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser');
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testMigrateFrom()
    {
        if (!SodiumPasswordEncoder::isSupported()) {
            $this->markTestSkipped('Sodium is not available');
        }

        $factory = new EncoderFactory([
            'digest_encoder' => $digest = new MessageDigestPasswordEncoder('sha256'),
            'pbdkf2' => $digest = new MessageDigestPasswordEncoder('sha256'),
            'bcrypt_encoder' => ['algorithm' => 'bcrypt'],
            SomeUser::class => ['algorithm' => 'sodium', 'migrate_from' => ['bcrypt_encoder', 'digest_encoder']],
        ]);

        $encoder = $factory->getEncoder(SomeUser::class);
        $this->assertInstanceOf(MigratingPasswordEncoder::class, $encoder);

        $this->assertTrue($encoder->isPasswordValid((new SodiumPasswordEncoder())->encodePassword('foo', null), 'foo', null));
        $this->assertTrue($encoder->isPasswordValid((new NativePasswordEncoder(null, null, null, \PASSWORD_BCRYPT))->encodePassword('foo', null), 'foo', null));
        $this->assertTrue($encoder->isPasswordValid($digest->encodePassword('foo', null), 'foo', null));
    }

    public function testDefaultMigratingEncoders()
    {
        $this->assertInstanceOf(
            MigratingPasswordEncoder::class,
            (new EncoderFactory([SomeUser::class => ['class' => NativePasswordEncoder::class, 'arguments' => []]]))->getEncoder(SomeUser::class)
        );

        if (!SodiumPasswordEncoder::isSupported()) {
            return;
        }

        $this->assertInstanceOf(
            MigratingPasswordEncoder::class,
            (new EncoderFactory([SomeUser::class => ['class' => SodiumPasswordEncoder::class, 'arguments' => []]]))->getEncoder(SomeUser::class)
        );
    }
}

class SomeUser implements UserInterface
{
    public function getRoles(): array
    {
    }

    public function getPassword(): ?string
    {
    }

    public function getSalt(): ?string
    {
    }

    public function getUsername(): string
    {
    }

    public function eraseCredentials()
    {
    }
}

class SomeChildUser extends SomeUser
{
}

class EncAwareUser extends SomeUser implements EncoderAwareInterface
{
    public $encoderName = 'encoder_name';

    public function getEncoderName(): ?string
    {
        return $this->encoderName;
    }
}
