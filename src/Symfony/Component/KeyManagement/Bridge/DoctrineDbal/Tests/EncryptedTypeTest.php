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

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedType;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;

#[RequiresPhpExtension('openssl')]
class EncryptedTypeTest extends TestCase
{
    private AbstractPlatform $platform;
    private EnvelopeEncrypter $envelopes;

    protected function setUp(): void
    {
        $this->platform = new SQLitePlatform();
        $this->envelopes = new EnvelopeEncrypter(new OpenSslKms(new InMemoryKeyLoader([
            'app' => random_bytes(32),
        ])));
    }

    public function testRoundTripWithStringParent()
    {
        $type = $this->makeType(new StringType());

        $encrypted = $type->convertToDatabaseValue('hello@example.com', $this->platform);
        $this->assertNotSame('hello@example.com', $encrypted);
        $this->assertSame('hello@example.com', $type->convertToPHPValue($encrypted, $this->platform));
    }

    public function testRoundTripWithIntegerParent()
    {
        $type = $this->makeType(new IntegerType());

        $encrypted = $type->convertToDatabaseValue(42, $this->platform);
        $this->assertNotSame('42', $encrypted);
        $this->assertSame(42, $type->convertToPHPValue($encrypted, $this->platform));
    }

    public function testNullPassesThrough()
    {
        $type = $this->makeType(new StringType());

        $this->assertNull($type->convertToDatabaseValue(null, $this->platform));
        $this->assertNull($type->convertToPHPValue(null, $this->platform));
    }

    public function testCiphertextsAreNotDeterministic()
    {
        $type = $this->makeType(new StringType());

        $a = $type->convertToDatabaseValue('hello', $this->platform);
        $b = $type->convertToDatabaseValue('hello', $this->platform);

        $this->assertNotSame($a, $b, 'Each row carries its own envelope with a fresh DEK and IV.');
    }

    public function testAStoreBackedEncrypterMakesTheThirdArgumentAScope()
    {
        $store = new InMemoryDataKeyStore();
        $type = new EncryptedType(new StringType(), new StoredEnvelopeEncrypter($store), 'user.email');

        $stored = $type->convertToDatabaseValue('jane@example.com', $this->platform);
        $store->forget();

        $this->assertSame('jane@example.com', $type->convertToPHPValue($stored, $this->platform));
        $this->assertSame($store->current('user.email')->reference, Envelope::fromBytes($stored)->reference);
        $this->assertNull(Envelope::fromBytes($stored)->wrappedDek, 'the wrapped key stays in the store, not in the column.');
    }

    public function testRowsOfAScopeShareOneDataKey()
    {
        $store = new InMemoryDataKeyStore();
        $type = new EncryptedType(new StringType(), new StoredEnvelopeEncrypter($store), 'user.email');

        $first = Envelope::fromBytes($type->convertToDatabaseValue('one', $this->platform));
        $second = Envelope::fromBytes($type->convertToDatabaseValue('two', $this->platform));

        $this->assertSame($first->reference, $second->reference);
        $this->assertCount(1, iterator_to_array($store->all(), false), 'sharing the key is what spares the KMS round trips.');
    }

    /**
     * A column half migrated to the store: rows written before keep resolving through the KMS,
     * rows written after refer to a stored key, and one type reads both.
     */
    public function testAColumnMayHoldBothFormatsDuringAMigration()
    {
        $legacy = new EncryptedType(new StringType(), $this->envelopes, 'app');
        $before = $legacy->convertToDatabaseValue('written before', $this->platform);

        $migrating = new EncryptedType(new StringType(), new StoredEnvelopeEncrypter(new InMemoryDataKeyStore(), $this->envelopes), 'user.email');
        $after = $migrating->convertToDatabaseValue('written after', $this->platform);

        $this->assertSame('written before', $migrating->convertToPHPValue($before, $this->platform));
        $this->assertSame('written after', $migrating->convertToPHPValue($after, $this->platform));
    }

    /**
     * A column moved to another KMS: a decrypter routing on the key id reads what the old provider
     * wrote for as long as rows are left to rewrite, and a rewritten row comes back under the new
     * provider alone.
     */
    public function testAColumnIsMovedToAnotherKms()
    {
        $target = new EnvelopeEncrypter(new OpenSslKms(new InMemoryKeyLoader(['next' => random_bytes(32)])));
        $written = (new EncryptedType(new StringType(), $this->envelopes, 'app'))->convertToDatabaseValue('jane@example.com', $this->platform);

        $migrating = new EncryptedType(new StringType(), $this->routingOnKeyId($target), 'next');
        $rewritten = $migrating->convertToDatabaseValue($migrating->convertToPHPValue($written, $this->platform), $this->platform);

        $this->assertSame('next', Envelope::fromBytes($rewritten)->keyId);
        $this->assertSame('jane@example.com', (new EncryptedType(new StringType(), $target, 'next'))->convertToPHPValue($rewritten, $this->platform));
    }

    public function testAColumnHandedBackAsAStreamIsRead()
    {
        $type = $this->makeType(new StringType());
        $encrypted = $type->convertToDatabaseValue('jane@example.com', $this->platform);

        $this->assertSame('jane@example.com', $type->convertToPHPValue(self::asStream($encrypted), $this->platform));
    }

    public function testAnEmptyColumnHandedBackAsAStreamGoesToTheParentType()
    {
        $type = $this->makeType(new StringType());

        $this->assertSame('', $type->convertToPHPValue(self::asStream(''), $this->platform));
    }

    public function testABlobParentHandsItsValueOverAsAStream()
    {
        $type = $this->makeType(new BlobType());

        $encrypted = $type->convertToDatabaseValue(self::asStream('the file bytes'), $this->platform);

        $this->assertSame('the file bytes', stream_get_contents($type->convertToPHPValue($encrypted, $this->platform)));
    }

    public function testMalformedColumnIsRejected()
    {
        $type = $this->makeType(new StringType());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a valid KeyManagement envelope');
        $type->convertToPHPValue('not-an-envelope', $this->platform);
    }

    /**
     * PHP records the arguments of every frame of a stack trace, so a backend failing while a column
     * is being written would carry the column value, in clear, into the logs.
     */
    public function testTheColumnValueDoesNotReachStackTraces()
    {
        $ignoreArguments = (string) \ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');

        $type = new EncryptedType(new StringType(), new class implements EnvelopeEncrypterInterface, EnvelopeDecrypterInterface {
            public function encrypt(string $key, #[\SensitiveParameter] string $plaintext, string $aad = ''): Envelope
            {
                throw new \RuntimeException('The backend is down.');
            }

            public function decrypt(Envelope $envelope, string $aad = ''): string
            {
                throw new \RuntimeException('The backend is down.');
            }
        }, 'app');

        try {
            $type->convertToDatabaseValue('hello@example.com', $this->platform);
            $this->fail('The conversion was expected to throw.');
        } catch (\RuntimeException $e) {
        } finally {
            ini_set('zend.exception_ignore_args', $ignoreArguments);
        }

        $redacted = false;
        foreach ($e->getTrace() as $frame) {
            $redacted = $redacted || \in_array(true, array_map(static fn ($argument): bool => $argument instanceof \SensitiveParameterValue, $frame['args'] ?? []), true);
            $this->assertNotContains('hello@example.com', $frame['args'] ?? [], \sprintf('%s%s%s() carries the column value in clear.', $frame['class'] ?? '', $frame['type'] ?? '', $frame['function']));
        }

        $this->assertTrue($redacted, 'nothing was redacted at all, so the assertion above would hold whatever the parameters are declared like.');
    }

    public function testGetBindingTypeIsBinary()
    {
        $this->assertSame(ParameterType::BINARY, $this->makeType(new StringType())->getBindingType());
    }

    public function testGetSqlDeclarationIsBinary()
    {
        $type = $this->makeType(new StringType());

        $this->assertStringContainsStringIgnoringCase('blob', $type->getSQLDeclaration([], $this->platform));
    }

    public function testTheColumnIsUnboundedWhateverLengthTheMappingDeclares()
    {
        $type = $this->makeType(new StringType());

        $this->assertSame('LONGBLOB', $type->getSQLDeclaration(['length' => 180], new MySQLPlatform()));
        $this->assertSame('BYTEA', $type->getSQLDeclaration(['length' => 180], new PostgreSQLPlatform()));
        $this->assertSame('BLOB', $type->getSQLDeclaration(['length' => 180], new SQLitePlatform()));
    }

    private function makeType(Type $parent): EncryptedType
    {
        return new EncryptedType($parent, $this->envelopes, 'app');
    }

    /**
     * Reads what the old KMS wrote and writes everything through the new one, which is what a
     * column being moved to another provider is handed while it is being rewritten.
     */
    private function routingOnKeyId(EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $target): EnvelopeEncrypterInterface&EnvelopeDecrypterInterface
    {
        return new class($target, $this->envelopes) implements EnvelopeEncrypterInterface, EnvelopeDecrypterInterface {
            public function __construct(
                private EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $target,
                private EnvelopeDecrypterInterface $legacy,
            ) {
            }

            public function encrypt(string $key, #[\SensitiveParameter] string $plaintext, string $aad = ''): Envelope
            {
                return $this->target->encrypt($key, $plaintext, $aad);
            }

            public function decrypt(Envelope $envelope, string $aad = ''): string
            {
                return 'app' === $envelope->keyId ? $this->legacy->decrypt($envelope, $aad) : $this->target->decrypt($envelope, $aad);
            }
        };
    }

    /**
     * Mimics the drivers and the blob types that carry a binary value as a stream rather than as a
     * string.
     *
     * @return resource
     */
    private static function asStream(string $value)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $value);
        rewind($stream);

        return $stream;
    }
}
