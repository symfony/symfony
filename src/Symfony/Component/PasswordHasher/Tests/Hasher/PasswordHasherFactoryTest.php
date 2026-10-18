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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\MessageDigestPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\MigratingPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherAwareInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher;
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

        $hasher = $factory->getPasswordHasher($this->createStub(PasswordAuthenticatedUserInterface::class));
        $expectedHasher = new MessageDigestPasswordHasher('sha512', true, 5);

        $this->assertEquals($expectedHasher->hash('foo', 'moo'), $hasher->hash('foo', 'moo'));
    }

    public function testGetHasherWithService()
    {
        $factory = new PasswordHasherFactory([
            PasswordAuthenticatedUserInterface::class => new MessageDigestPasswordHasher('sha1'),
        ]);

        $hasher = $factory->getPasswordHasher($this->createStub(PasswordAuthenticatedUserInterface::class));
        $expectedHasher = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedHasher->hash('foo', ''), $hasher->hash('foo', ''));
    }

    public function testGetHasherWithInstance()
    {
        $factory = new PasswordHasherFactory([
            PasswordAuthenticatedUserInterface::class => ['instance' => new MessageDigestPasswordHasher('sha1')],
        ]);

        $hasher = $factory->getPasswordHasher($this->createStub(PasswordAuthenticatedUserInterface::class));
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

        $hasher = $factory->getPasswordHasher(new HasherAwareUser());
        $expectedHasher = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedHasher->hash('foo', ''), $hasher->hash('foo', ''));
    }

    public function testGetNullNamedHasherForHasherAware()
    {
        $factory = new PasswordHasherFactory([
            HasherAwareUser::class => new MessageDigestPasswordHasher('sha1'),
            'hasher_name' => new MessageDigestPasswordHasher('sha256'),
        ]);

        $user = new HasherAwareUser();
        $user->hasherName = null;
        $hasher = $factory->getPasswordHasher($user);
        $expectedHasher = new MessageDigestPasswordHasher('sha1');
        $this->assertEquals($expectedHasher->hash('foo', ''), $hasher->hash('foo', ''));
    }

    public function testGetInvalidNamedHasherForHasherAware()
    {
        $factory = new PasswordHasherFactory([
            HasherAwareUser::class => new MessageDigestPasswordHasher('sha1'),
            'hasher_name' => new MessageDigestPasswordHasher('sha256'),
        ]);

        $user = new HasherAwareUser();
        $user->hasherName = 'invalid_hasher_name';

        $this->expectException(\RuntimeException::class);

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

        $this->assertTrue($hasher->verify((new SodiumPasswordHasher())->hash('foo'), 'foo', null));
        $this->assertTrue($hasher->verify((new NativePasswordHasher(null, null, null, \PASSWORD_BCRYPT))->hash('foo'), 'foo', null));
        $this->assertTrue($hasher->verify($digest->hash('foo'), 'foo', null));
        $this->assertStringStartsWith(\SODIUM_CRYPTO_PWHASH_STRPREFIX, $hasher->hash('foo', null));
    }

    public function testMissingClass()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"class" must be set in {"arguments":[]}');

        (new PasswordHasherFactory([SomeUser::class => ['arguments' => []]]))->getPasswordHasher(SomeUser::class);
    }

    public function testMissingArguments()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"arguments" must be set in {"class":"stdClass"}');

        (new PasswordHasherFactory([SomeUser::class => ['class' => \stdClass::class]]))->getPasswordHasher(SomeUser::class);
    }

    #[DataProvider('provideLegacyMigrationTargets')]
    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testLegacyMigrationTargetIsDeprecated(array $config, string $class)
    {
        $factory = new PasswordHasherFactory([SomeUser::class => $config + ['migrate_from' => ['sha1']]]);

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/password-hasher 8.2: Configuring "migrate_from" for a "%s" hasher is deprecated because this hasher never needs rehashing, so the listed hashers are never used. Use "auto" or a custom hasher instead.', $class));

        $hasher = $factory->getPasswordHasher(SomeUser::class);

        $this->assertInstanceOf(MigratingPasswordHasher::class, $hasher);
        $this->assertTrue($hasher->verify($hasher->hash('password'), 'password'));
    }

    public static function provideLegacyMigrationTargets(): iterable
    {
        yield 'message digest algorithm' => [['algorithm' => 'sha512'], MessageDigestPasswordHasher::class];
        yield 'PBKDF2 algorithm' => [['algorithm' => 'pbkdf2'], Pbkdf2PasswordHasher::class];
        yield 'plaintext algorithm' => [['algorithm' => 'plaintext'], PlaintextPasswordHasher::class];
        yield 'message digest instance' => [['instance' => new MessageDigestPasswordHasher()], MessageDigestPasswordHasher::class];
        yield 'PBKDF2 instance' => [['instance' => new Pbkdf2PasswordHasher()], Pbkdf2PasswordHasher::class];
        yield 'plaintext instance' => [['instance' => new PlaintextPasswordHasher()], PlaintextPasswordHasher::class];
    }

    public function testLegacyTargetWithoutMigrationSources()
    {
        $target = new MessageDigestPasswordHasher();
        $factory = new PasswordHasherFactory([
            SomeUser::class => ['instance' => $target, 'migrate_from' => []],
        ]);

        $hasher = $factory->getPasswordHasher(SomeUser::class);

        $this->assertTrue($hasher->verify($target->hash('password'), 'password'));
    }

    public function testMigrateFromWithAutoHasher()
    {
        $legacy = new MessageDigestPasswordHasher('sha1');
        $factory = new PasswordHasherFactory([
            SomeUser::class => ['algorithm' => 'auto', 'hash_algorithm' => 'sha512', 'migrate_from' => ['legacy']],
            'legacy' => $legacy,
        ]);

        $hasher = $factory->getPasswordHasher(SomeUser::class);

        $this->assertTrue($hasher->verify($legacy->hash('password'), 'password'));
    }

    public function testMigrationToCustomLegacyHasher()
    {
        $target = new class extends MessageDigestPasswordHasher {
            public function needsRehash(string $hashedPassword): bool
            {
                return true;
            }
        };
        $legacy = new PlaintextPasswordHasher();
        $factory = new PasswordHasherFactory([
            SomeUser::class => ['instance' => $target, 'migrate_from' => ['legacy']],
            'legacy' => $legacy,
        ]);

        $hasher = $factory->getPasswordHasher(SomeUser::class);

        $this->assertTrue($hasher->verify($legacy->hash('password'), 'password'));
        $this->assertSame($target->hash('password'), $hasher->hash('password'));
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

    public function testMigrateFromWithCustomInstance()
    {
        if (!SodiumPasswordHasher::isSupported()) {
            $this->markTestSkipped('Sodium is not available');
        }

        $sodium = new SodiumPasswordHasher();

        $factory = new PasswordHasherFactory([
            'digest_hasher' => $digest = new MessageDigestPasswordHasher('sha256'),
            SomeUser::class => ['instance' => $sodium, 'migrate_from' => ['bcrypt', 'digest_hasher']],
        ]);

        $hasher = $factory->getPasswordHasher(SomeUser::class);
        $this->assertInstanceOf(MigratingPasswordHasher::class, $hasher);

        $this->assertTrue($hasher->verify((new SodiumPasswordHasher())->hash('foo', null), 'foo', null));
        $this->assertTrue($hasher->verify((new NativePasswordHasher(null, null, null, \PASSWORD_BCRYPT))->hash('foo', null), 'foo', null));
        $this->assertTrue($hasher->verify($digest->hash('foo', null), 'foo', null));
        $this->assertStringStartsWith(\SODIUM_CRYPTO_PWHASH_STRPREFIX, $hasher->hash('foo', null));
    }
}

class SomeUser implements PasswordAuthenticatedUserInterface
{
    public function getPassword(): ?string
    {
    }
}

class SomeChildUser extends SomeUser
{
}

class HasherAwareUser extends SomeUser implements PasswordHasherAwareInterface
{
    public ?string $hasherName = 'hasher_name';

    public function getPasswordHasherName(): ?string
    {
        return $this->hasherName;
    }
}
