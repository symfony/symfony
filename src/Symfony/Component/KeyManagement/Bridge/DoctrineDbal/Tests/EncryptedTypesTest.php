<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineDbal\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedType;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\Tests\Fixtures\ContextType;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;

/**
 * Each test declares a type name of its own rather than leaking one into the next.
 *
 * The type registry is global and has no way of forgetting a name.
 */
#[RequiresPhpExtension('openssl')]
class EncryptedTypesTest extends TestCase
{
    private EnvelopeEncrypter $envelopes;

    protected function setUp(): void
    {
        $this->envelopes = new EnvelopeEncrypter(new OpenSslKms(new InMemoryKeyLoader([
            'app' => random_bytes(32),
            'other' => random_bytes(32),
        ])));
    }

    public function testADeclaredTypeWrapsItsParentAndEncryptsUnderItsKey()
    {
        $name = self::uniqueName();

        (new EncryptedTypes($this->envelopes, [$name => ['type' => 'string', 'key' => 'app']]))->register();

        $type = self::type($name);
        $this->assertInstanceOf(EncryptedType::class, $type);

        $platform = new SQLitePlatform();
        $stored = $type->convertToDatabaseValue('hello@example.com', $platform);

        $this->assertSame('app', Envelope::fromBytes($stored)->keyId, 'the declared key is the one the envelope records');
        $this->assertSame('hello@example.com', $type->convertToPHPValue($stored, $platform));
    }

    public function testSeveralTypesAreDeclaredAtOnce()
    {
        $string = self::uniqueName();
        $text = self::uniqueName();

        (new EncryptedTypes($this->envelopes, [
            $string => ['type' => 'string', 'key' => 'app'],
            $text => ['type' => 'text', 'key' => 'other'],
        ]))->register();

        $this->assertSame('app', Envelope::fromBytes(self::type($string)->convertToDatabaseValue('x', new SQLitePlatform()))->keyId);
        $this->assertSame('other', Envelope::fromBytes(self::type($text)->convertToDatabaseValue('x', new SQLitePlatform()))->keyId);
    }

    public function testDeclaringTwiceReplacesTheTypeInsteadOfFailing()
    {
        $name = self::uniqueName();
        $types = new EncryptedTypes($this->envelopes, [$name => ['type' => 'string', 'key' => 'app']]);

        $types->register();
        $first = self::type($name);

        $types->register();

        $this->assertNotSame($first, self::type($name));
        $this->assertSame('x', self::type($name)->convertToPHPValue(self::type($name)->convertToDatabaseValue('x', new SQLitePlatform()), new SQLitePlatform()));
    }

    public function testTheParentTypeIsTheOneTheConnectionResolves()
    {
        if (!method_exists(Configuration::class, 'setTypeProvider')) {
            $this->markTestSkipped('doctrine/dbal < 4.6 has no type provider per connection.');
        }

        $parent = self::uniqueName();
        $name = self::uniqueName();
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], (new Configuration())->setTypeProvider(new TypeRegistry([$parent => new StringType()])));

        (new EncryptedTypes($this->envelopes, [$name => ['type' => $parent, 'key' => 'app']]))->register($connection);

        $type = $connection->getConfiguration()->getTypeProvider()->get($name);
        $this->assertSame('x', $type->convertToPHPValue($type->convertToDatabaseValue('x', new SQLitePlatform()), new SQLitePlatform()));
    }

    public function testAReadOnlyTypeProviderCannotReceiveEncryptedTypes()
    {
        if (!method_exists(Configuration::class, 'setTypeProvider')) {
            $this->markTestSkipped('doctrine/dbal < 4.6 has no type provider per connection.');
        }

        $provider = new class implements \Doctrine\DBAL\Types\TypeProvider, \IteratorAggregate {
            public function get(string $name): Type
            {
                return new StringType();
            }

            public function has(string $name): bool
            {
                return 'string' === $name;
            }

            public function getIterator(): \Traversable
            {
                yield 'string' => new StringType();
            }
        };
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], (new Configuration())->setTypeProvider($provider));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Encrypted types require a writable Doctrine type registry.');

        (new EncryptedTypes($this->envelopes, [self::uniqueName() => ['type' => 'string', 'key' => 'app']]))->register($connection);
    }

    public function testTypesSharingAKeyButNotADoctrineTypeRefuseEachOtherSValues()
    {
        $notes = self::uniqueName();
        $roles = self::uniqueName();

        (new EncryptedTypes($this->envelopes, [
            $notes => ['type' => 'text', 'key' => 'app'],
            $roles => ['type' => 'json', 'key' => 'app'],
        ]))->register();

        $written = self::type($notes)->convertToDatabaseValue('{"role":"admin"}', new SQLitePlatform());

        $this->expectException(DecryptionFailedException::class);
        self::type($roles)->convertToPHPValue($written, new SQLitePlatform());
    }

    public function testTypesSharingAKeyAndADoctrineTypeReadEachOtherSValues()
    {
        $notes = self::uniqueName();
        $address = self::uniqueName();

        (new EncryptedTypes($this->envelopes, [
            $notes => ['type' => 'text', 'key' => 'app'],
            $address => ['type' => 'text', 'key' => 'app'],
        ]))->register();

        $written = self::type($notes)->convertToDatabaseValue('a note', new SQLitePlatform());

        $this->assertSame('a note', self::type($address)->convertToPHPValue($written, new SQLitePlatform()), 'the declaration name is not what identifies a value, so the order they are declared in cannot matter.');
    }

    public function testRenamingADeclarationLeavesItsValuesReadable()
    {
        $before = self::uniqueName();
        $after = self::uniqueName();

        (new EncryptedTypes($this->envelopes, [$before => ['type' => 'text', 'key' => 'app']]))->register();
        $written = self::type($before)->convertToDatabaseValue('a note', new SQLitePlatform());

        (new EncryptedTypes($this->envelopes, [$after => ['type' => 'text', 'key' => 'app']]))->register();

        $this->assertSame('a note', self::type($after)->convertToPHPValue($written, new SQLitePlatform()));
    }

    public function testAParentAliasSurvivesAClassOverride()
    {
        $parent = self::uniqueName();
        $name = self::uniqueName();
        self::registry()->register($parent, new StringType());
        $types = new EncryptedTypes($this->envelopes, [$name => ['type' => $parent, 'key' => 'app']]);
        $types->register();
        $written = self::type($name)->convertToDatabaseValue('a note', new SQLitePlatform());

        self::registry()->override($parent, new ContextType());
        $types->register();

        $this->assertSame('a note', self::type($name)->convertToPHPValue($written, new SQLitePlatform()));
    }

    public function testDifferentParentAliasesRefuseValuesOfTheSameClass()
    {
        $firstParent = self::uniqueName();
        $secondParent = self::uniqueName();
        $first = self::uniqueName();
        $second = self::uniqueName();
        self::registry()->register($firstParent, new StringType());
        self::registry()->register($secondParent, new StringType());
        (new EncryptedTypes($this->envelopes, [
            $first => ['type' => $firstParent, 'key' => 'app'],
            $second => ['type' => $secondParent, 'key' => 'app'],
        ]))->register();
        $written = self::type($first)->convertToDatabaseValue('a note', new SQLitePlatform());

        $this->expectException(DecryptionFailedException::class);
        self::type($second)->convertToPHPValue($written, new SQLitePlatform());
    }

    public function testDirectAndRegisteredTypesUseTheSameParentAlias()
    {
        $name = self::uniqueName();
        (new EncryptedTypes($this->envelopes, [$name => ['type' => 'string', 'key' => 'app']]))->register();
        $direct = new EncryptedType(new StringType(), $this->envelopes, 'app', 'string');
        $registered = self::type($name);
        $platform = new SQLitePlatform();

        $this->assertSame('direct', $registered->convertToPHPValue($direct->convertToDatabaseValue('direct', $platform), $platform));
        $this->assertSame('registered', $direct->convertToPHPValue($registered->convertToDatabaseValue('registered', $platform), $platform));
    }

    public function testADeclarationWithoutAKeyIsReported()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The encrypted type "app_email" must declare a "key" as a string.');

        (new EncryptedTypes($this->envelopes, ['app_email' => ['type' => 'string']]))->register();
    }

    public function testADeclarationWithoutAParentTypeIsReported()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The encrypted type "app_email" must declare a "type" as a string.');

        (new EncryptedTypes($this->envelopes, ['app_email' => ['key' => 'app']]))->register();
    }

    public function testAnUnknownParentTypeIsReported()
    {
        $this->expectException(\Throwable::class);

        (new EncryptedTypes($this->envelopes, [self::uniqueName() => ['type' => 'no_such_type', 'key' => 'app']]))->register();
    }

    private static function uniqueName(): string
    {
        return 'encrypted_'.bin2hex(random_bytes(6));
    }

    private static function type(string $name): Type
    {
        return self::registry()->get($name);
    }

    private static function registry(): TypeRegistry
    {
        return method_exists(Configuration::class, 'getTypeProvider') ? (new Configuration())->getTypeProvider() : Type::getTypeRegistry();
    }
}
