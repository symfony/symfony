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

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedType;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;

/**
 * The type registry is global and has no way of forgetting a name, so each test declares one of
 * its own rather than leaking a type into the next.
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

        $type = Type::getType($name);
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

        $this->assertSame('app', Envelope::fromBytes(Type::getType($string)->convertToDatabaseValue('x', new SQLitePlatform()))->keyId);
        $this->assertSame('other', Envelope::fromBytes(Type::getType($text)->convertToDatabaseValue('x', new SQLitePlatform()))->keyId);
    }

    /**
     * What a rebooted kernel does: the registry outlives the container, so the second pass has to
     * replace the types of the first rather than refuse to declare them.
     */
    public function testDeclaringTwiceReplacesTheTypeInsteadOfFailing()
    {
        $name = self::uniqueName();
        $types = new EncryptedTypes($this->envelopes, [$name => ['type' => 'string', 'key' => 'app']]);

        $types->register();
        $first = Type::getType($name);

        $types->register();

        $this->assertNotSame($first, Type::getType($name));
        $this->assertSame('x', Type::getType($name)->convertToPHPValue(Type::getType($name)->convertToDatabaseValue('x', new SQLitePlatform()), new SQLitePlatform()));
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
}
