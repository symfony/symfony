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
use Symfony\Component\PasswordHasher\Hasher\MessageDigestPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\MigratingPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherAwareInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\SodiumPasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class PasswordHasherFactoryTest extends TestCase
{
    public function testGetHasherWithMessageDigestHasher()
    {
        $factory = new PasswordHasherFactory([PasswordAuthenticatedUserInterface::class => [
            'class' => MessageDigestPasswordHasher::class,
            'arguments' => ['sha512', true, 5],
        ]]);

        $hasher = $factory->getPasswordHasher($this->createMock(PasswordAuthenticatedUserInterface::class));
        $expectedHasher = new MessageDigestPasswordHasher('sha512', true, 5);

        $this->assertEquals($expectedHasher->hash('foo', 'moo'), $hasher->hash('foo', 'moo'));
    }

    public function testGetHasherWithService()
    {
        $factory = new PasswordHasherFactory([
            PasswordAuthenticatedUserInterface::class => new MessageDigestPasswordHasher('sha1'),
        ]);

        $hasher = $factory->getPasswordHasher($this->createMock(PasswordAuthenticatedUserInterface::class));
        $expectedHasher = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedHasher->hash('foo', ''), $hasher->hash('foo', ''));
    }

    public function testGetHasherWithClassName()
    {
        $factory = new PasswordHasherFactory([
            PasswordAuthenticatedUserInterface::class => new MessageDigestPasswordHasher('sha1'),
        ]);

        $hasher = $factory->getPasswordHasher(SomeChildUser::class);
        $expectedHasher = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedHasher->hash('foo', ''), $hasher->hash('foo', ''));
    }

    public function testGetHasherConfiguredForConcreteClassWithService()
    {
        $factory = new PasswordHasherFactory([
            'Symfony\Component\Security\Core\User\InMemoryUser' => new MessageDigestPasswordHasher('sha1'),
        ]);

        $hasher = $factory->getPasswordHasher(new InMemoryUser('user', 'pass'));
        $expectedHasher = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedHasher->hash('foo', ''), $hasher->hash('foo', ''));
    }

    public function testGetHasherConfiguredForConcreteClassWithClassName()
    {
        $factory = new PasswordHasherFactory([
            'Symfony\Component\PasswordHasher\Tests\Hasher\SomeUser' => new MessageDigestPasswordHasher('sha1'),
        ]);

        $hasher = $factory->getPasswordHasher(SomeChildUser::class);
        $expectedHasher = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedHasher->hash('foo', ''), $hasher->hash('foo', ''));
    }

    public function testGetHasherConfiguredWithAuto()
    {
        $factory = new PasswordHasherFactory([
            'auto' => ['algorithm' => 'auto'],
        ]);

        $hasher = $factory->getPasswordHasher('auto');
        $this->assertInstanceOf(PasswordHasherInterface::class, $hasher);
    }

    public function testGetNamedHasherForHasherAware()
    {
        $factory = new PasswordHasherFactory([
            HasherAwareUser::class => new MessageDigestPasswordHasher('sha256'),
            'hasher_name' => new MessageDigestPasswordHasher('sha1'),
        ]);

        $hasher = $factory->getPasswordHasher(new HasherAwareUser('user', 'pass'));
        $expectedHasher = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedHasher->hash('foo', ''), $hasher->hash('foo', ''));
    }

    public function testGetNullNamedHasherForHasherAware()
    {
        $factory = new PasswordHasherFactory([
            HasherAwareUser::class => new MessageDigestPasswordHasher('sha1'),
            'hasher_name' => new MessageDigestPasswordHasher('sha256'),
        ]);

        $user = new HasherAwareUser('mathilde', 'krogulec');
        $user->hasherName = null;
        $hasher = $factory->getPasswordHasher($user);
        $expectedHasher = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedHasher->hash('foo', ''), $hasher->hash('foo', ''));
    }

    public function testGetInvalidNamedHasherForHasherAware()
    {
        $this->expectException(\RuntimeException::class);
        $factory = new PasswordHasherFactory([
            HasherAwareUser::class => new MessageDigestPasswordHasher('sha1'),
            'hasher_name' => new MessageDigestPasswordHasher('sha256'),
        ]);

        $user = new HasherAwareUser('user', 'pass');
        $user->hasherName = 'invalid_hasher_name';
        $factory->getPasswordHasher($user);
    }

    public function testGetHasherForHasherAwareWithClassName()
    {
        $factory = new PasswordHasherFactory([
            HasherAwareUser::class => new MessageDigestPasswordHasher('sha1'),
            'hasher_name' => new MessageDigestPasswordHasher('sha256'),
        ]);

        $hasher = $factory->getPasswordHasher(HasherAwareUser::class);
        $expectedHasher = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedHasher->hash('foo', ''), $hasher->hash('foo', ''));
    }

    public function testMigrateFrom()
    {
        if (!SodiumPasswordHasher::isSupported()) {
            $this->markTestSkipped('Sodium is not available');
        }

        $factory = new PasswordHasherFactory([
            'digest_hasher' => $digest = new MessageDigestPasswordHasher('sha256'),
            SomeUser::class => ['algorithm' => 'sodium', 'migrate_from' => ['bcrypt', 'digest_hasher']],
        ]);

        $hasher = $factory->getPasswordHasher(SomeUser::class);
        $this->assertInstanceOf(MigratingPasswordHasher::class, $hasher);

        $this->assertTrue($hasher->verify((new SodiumPasswordHasher())->hash('foo', null), 'foo', null));
        $this->assertTrue($hasher->verify((new NativePasswordHasher(null, null, null, \PASSWORD_BCRYPT))->hash('foo', null), 'foo', null));
        $this->assertTrue($hasher->verify($digest->hash('foo', null), 'foo', null));
        $this->assertStringStartsWith(\SODIUM_CRYPTO_PWHASH_STRPREFIX, $hasher->hash('foo', null));
    }

    public function testDefaultMigratingHashers()
    {
        $this->assertInstanceOf(
            MigratingPasswordHasher::class,
            (new PasswordHasherFactory([SomeUser::class => ['class' => NativePasswordHasher::class, 'arguments' => []]]))->getPasswordHasher(SomeUser::class)
        );

        $this->assertInstanceOf(
            MigratingPasswordHasher::class,
            (new PasswordHasherFactory([SomeUser::class => ['algorithm' => 'bcrypt', 'cost' => 11]]))->getPasswordHasher(SomeUser::class)
        );

        if (!SodiumPasswordHasher::isSupported()) {
            return;
        }

        $this->assertInstanceOf(
            MigratingPasswordHasher::class,
            (new PasswordHasherFactory([SomeUser::class => ['class' => SodiumPasswordHasher::class, 'arguments' => []]]))->getPasswordHasher(SomeUser::class)
        );
    }
}

class SomeUser implements PasswordAuthenticatedUserInterface
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

class HasherAwareUser extends SomeUser implements PasswordHasherAwareInterface
{
    public $hasherName = 'hasher_name';

    public function getPasswordHasherName(): ?string
    {
        return $this->hasherName;
    }
}
