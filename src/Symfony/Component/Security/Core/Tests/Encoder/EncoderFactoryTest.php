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
use Symfony\Component\PasswordHasher\Hasher\MessageDigestPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherAwareInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher;
use Symfony\Component\Security\Core\Encoder\EncoderAwareInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\MigratingPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\NativePasswordEncoder;
use Symfony\Component\Security\Core\Encoder\SelfSaltingEncoderInterface;
use Symfony\Component\Security\Core\Encoder\SodiumPasswordEncoder;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @group legacy
 */
class EncoderFactoryTest extends TestCase
{
    public function testGetEncoderWithMessageDigestEncoder()
    {
        $factory = new EncoderFactory(['Symfony\Component\Security\Core\User\UserInterface' => [
            'class' => 'Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder',
            'arguments' => ['sha512', true, 5],
        ]]);

        $encoder = $factory->getEncoder($this->createMock(UserInterface::class));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha512', true, 5);

        $this->assertEquals($expectedEncoder->encodePassword('foo', 'moo'), $encoder->encodePassword('foo', 'moo'));
    }

    public function testGetEncoderWithService()
    {
        $factory = new EncoderFactory([
            'Symfony\Component\Security\Core\User\UserInterface' => new MessageDigestPasswordEncoder('sha1'),
        ]);

        $encoder = $factory->getEncoder($this->createMock(UserInterface::class));
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
        $this->expectException(\RuntimeException::class);
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
            SomeUser::class => ['algorithm' => 'sodium', 'migrate_from' => ['bcrypt', 'digest_encoder']],
        ]);

        $encoder = $factory->getEncoder(SomeUser::class);
        $this->assertInstanceOf(MigratingPasswordEncoder::class, $encoder);

        $this->assertTrue($encoder->isPasswordValid((new SodiumPasswordEncoder())->encodePassword('foo', null), 'foo', null));
        $this->assertTrue($encoder->isPasswordValid((new NativePasswordEncoder(null, null, null, \PASSWORD_BCRYPT))->encodePassword('foo', null), 'foo', null));
        $this->assertTrue($encoder->isPasswordValid($digest->encodePassword('foo', null), 'foo', null));
        $this->assertStringStartsWith(\SODIUM_CRYPTO_PWHASH_STRPREFIX, $encoder->encodePassword('foo', null));
    }

    public function testDefaultMigratingEncoders()
    {
        $this->assertInstanceOf(
            MigratingPasswordEncoder::class,
            (new EncoderFactory([SomeUser::class => ['class' => NativePasswordEncoder::class, 'arguments' => []]]))->getEncoder(SomeUser::class)
        );

        $this->assertInstanceOf(
            MigratingPasswordEncoder::class,
            (new EncoderFactory([SomeUser::class => ['algorithm' => 'bcrypt', 'cost' => 11]]))->getEncoder(SomeUser::class)
        );

        if (!SodiumPasswordEncoder::isSupported()) {
            return;
        }

        $this->assertInstanceOf(
            MigratingPasswordEncoder::class,
            (new EncoderFactory([SomeUser::class => ['class' => SodiumPasswordEncoder::class, 'arguments' => []]]))->getEncoder(SomeUser::class)
        );
    }

    public function testHasherAwareCompat()
    {
        $factory = new PasswordHasherFactory([
            'encoder_name' => new MessageDigestPasswordHasher('sha1'),
        ]);

        $encoder = $factory->getPasswordHasher(new HasherAwareUser('user', 'pass'));
        $expectedEncoder = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedEncoder->hash('foo', ''), $encoder->hash('foo', ''));
    }

    public function testLegacyPasswordHasher()
    {
        $factory = new EncoderFactory([
            SomeUser::class => new PlaintextPasswordHasher(),
        ]);

        $encoder = $factory->getEncoder(new SomeUser());
        self::assertNotInstanceOf(SelfSaltingEncoderInterface::class, $encoder);
        self::assertSame('foo{bar}', $encoder->encodePassword('foo', 'bar'));
    }

    public function testPasswordHasher()
    {
        $factory = new EncoderFactory([
            SomeUser::class => new NativePasswordHasher(),
        ]);

        $encoder = $factory->getEncoder(new SomeUser());
        self::assertInstanceOf(SelfSaltingEncoderInterface::class, $encoder);
        self::assertTrue($encoder->isPasswordValid($encoder->encodePassword('foo', null), 'foo', null));
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

    public function getUserIdentifier(): string
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

class HasherAwareUser extends SomeUser implements PasswordHasherAwareInterface
{
    public $hasherName = 'encoder_name';

    public function getPasswordHasherName(): ?string
    {
        return $this->hasherName;
    }
}
