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
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedType;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\Tests\Fixtures\ContextType;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\Tests\Fixtures\ContextTypeExtra;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\Local\SealedBoxKms;
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

    #[DataProvider('provideFloats')]
    public function testAFloatParentRoundTripsExactly(float $value)
    {
        $type = $this->makeType(new FloatType());

        $this->assertSame($value, $type->convertToPHPValue($type->convertToDatabaseValue($value, $this->platform), $this->platform));
    }

    public static function provideFloats(): iterable
    {
        yield 'a sum that does not round' => [0.1 + 0.2];
        yield 'pi' => [\M_PI];
        yield 'an integer' => [1.0];
        yield 'a negative zero' => [-0.0];
        yield 'a small exponent' => [1.0E-20];
        yield 'the smallest positive' => [\PHP_FLOAT_MIN];
    }

    #[DataProvider('provideNonFiniteFloats')]
    public function testANonFiniteFloatIsRefused(float $value)
    {
        $type = $this->makeType(new FloatType());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not finite');
        $type->convertToDatabaseValue($value, $this->platform);
    }

    public static function provideNonFiniteFloats(): iterable
    {
        yield 'infinity' => [\INF];
        yield 'negative infinity' => [-\INF];
        yield 'not a number' => [\NAN];
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

    public function testAColumnMayHoldBothFormatsDuringAMigration()
    {
        $legacy = new EncryptedType(new StringType(), $this->envelopes, 'app');
        $before = $legacy->convertToDatabaseValue('written before', $this->platform);

        $migrating = new EncryptedType(new StringType(), new StoredEnvelopeEncrypter(new InMemoryDataKeyStore(), $this->envelopes), 'app');
        $after = $migrating->convertToDatabaseValue('written after', $this->platform);

        $this->assertSame('written before', $migrating->convertToPHPValue($before, $this->platform));
        $this->assertSame('written after', $migrating->convertToPHPValue($after, $this->platform));
    }

    public function testAValueWithoutTheContextIsRefused()
    {
        $written = (string) $this->envelopes->encrypt('app', 'a note');
        $type = $this->makeType(new StringType());

        $this->expectException(DecryptionFailedException::class);
        $type->convertToPHPValue($written, $this->platform);
    }

    #[TestWith(['message'])]
    #[TestWith([''])]
    #[TestWith(['0'])]
    #[TestWith(["message\0name"])]
    public function testAStableTypeNameSurvivesAParentClassChange(string $name)
    {
        $before = new EncryptedType(new StringType(), $this->envelopes, 'app', $name);
        $after = new EncryptedType(new ContextType(), $this->envelopes, 'app', $name);

        $written = $before->convertToDatabaseValue('a note', $this->platform);

        $this->assertSame('a note', $after->convertToPHPValue($written, $this->platform));
    }

    #[TestWith(['message', 'other'])]
    #[TestWith(['0', '00'])]
    #[TestWith(['', '0'])]
    #[TestWith(['', StringType::class])]
    public function testDifferentTypeNamesRefuseValuesOfTheSameParentClass(string $before, string $after)
    {
        $first = new EncryptedType(new StringType(), $this->envelopes, 'app', $before);
        $second = new EncryptedType(new StringType(), $this->envelopes, 'app', $after);

        $written = $first->convertToDatabaseValue('a note', $this->platform);

        $this->expectException(DecryptionFailedException::class);
        $second->convertToPHPValue($written, $this->platform);
    }

    public function testANullTypeNameKeepsTheParentClassFallback()
    {
        $before = new EncryptedType(new StringType(), $this->envelopes, 'app');
        $after = new EncryptedType(new StringType(), $this->envelopes, 'app', null);
        $explicit = new EncryptedType(new StringType(), $this->envelopes, 'app', StringType::class);

        $written = $before->convertToDatabaseValue('a note', $this->platform);

        $this->assertSame('a note', $after->convertToPHPValue($written, $this->platform));
        $this->assertSame('a note', $explicit->convertToPHPValue($written, $this->platform));
    }

    public function testATypeNameTooLongForItsLengthPrefixIsRefused()
    {
        $this->expectException(InvalidArgumentException::class);

        new EncryptedType(new StringType(), $this->envelopes, 'app', str_repeat('a', 65536));
    }

    #[TestWith(['message', 'messageExtra'])]
    #[TestWith(["message\0", "message\0Extra"])]
    public function testATypeNameCannotBeConfusedWithADecoratorContext(string $before, string $after)
    {
        $encrypter = new StoredEnvelopeEncrypter(new InMemoryDataKeyStore());
        $seen = [];
        $first = new EncryptedType(new StringType(), self::appendingToTheAad($encrypter, 'Extra/row-42', $seen), 'user.data', $before);
        $second = new EncryptedType(new StringType(), self::appendingToTheAad($encrypter, '/row-42', $seen), 'user.data', $after);

        $written = $first->convertToDatabaseValue('a note', $this->platform);

        $this->expectException(DecryptionFailedException::class);
        $second->convertToPHPValue($written, $this->platform);
    }

    public function testAParentClassCannotBeConfusedWithADecoratorContext()
    {
        $encrypter = new StoredEnvelopeEncrypter(new InMemoryDataKeyStore());
        $seen = [];
        $first = new EncryptedType(new ContextType(), self::appendingToTheAad($encrypter, 'Extra/row-42', $seen), 'user.data');
        $second = new EncryptedType(new ContextTypeExtra(), self::appendingToTheAad($encrypter, '/row-42', $seen), 'user.data');

        $written = $first->convertToDatabaseValue('a note', $this->platform);

        $this->expectException(DecryptionFailedException::class);
        $second->convertToPHPValue($written, $this->platform);
    }

    public function testAParentClassTooLongForItsLengthPrefixIsRefused()
    {
        $class = 'EncryptedParent'.str_repeat('A', 65536 - \strlen('EncryptedParent'));
        $parent = class_exists($class, false) ? new $class() : eval('class '.$class.' extends \\Doctrine\\DBAL\\Types\\StringType {} return new '.$class.'();');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The parent type name of an encrypted type is at most 65535 bytes long, 65536 given.');

        new EncryptedType($parent, $this->envelopes, 'app');
    }

    public function testAValueOfAnotherTypeOfTheSameScopeIsRefused()
    {
        $encrypter = new StoredEnvelopeEncrypter(new InMemoryDataKeyStore());
        $notes = new EncryptedType(new TextType(), $encrypter, 'user.data');
        $label = new EncryptedType(new StringType(), $encrypter, 'user.data');

        $written = $notes->convertToDatabaseValue('a note', $this->platform);

        $this->expectException(DecryptionFailedException::class);
        $label->convertToPHPValue($written, $this->platform);
    }

    public function testAValueOfAnotherScopeOfTheSameTypeIsRefused()
    {
        $encrypter = new StoredEnvelopeEncrypter(new InMemoryDataKeyStore());
        $notes = new EncryptedType(new TextType(), $encrypter, 'user.notes');
        $other = new EncryptedType(new TextType(), $encrypter, 'user.email');

        $written = $notes->convertToDatabaseValue('a note', $this->platform);

        $this->expectException(DecryptionFailedException::class);
        $other->convertToPHPValue($written, $this->platform);
    }

    public function testTwoTypesOfTheSameScopeAndTypeReadEachOther()
    {
        $encrypter = new StoredEnvelopeEncrypter(new InMemoryDataKeyStore());
        $notes = new EncryptedType(new TextType(), $encrypter, 'user.data');
        $elsewhere = new EncryptedType(new TextType(), $encrypter, 'user.data');

        $written = $notes->convertToDatabaseValue('a note', $this->platform);

        $this->assertSame('a note', $elsewhere->convertToPHPValue($written, $this->platform), 'the pair is the identity, so two columns sharing it are one: give them their own scope to keep them apart.');
    }

    #[RequiresPhpExtension('sodium')]
    public function testTheContextIsAuthenticatedOnABackendThatRefusesAuthenticatedData()
    {
        $envelopes = new EnvelopeEncrypter(new SealedBoxKms(new InMemoryKeyLoader(['app' => sodium_crypto_box_keypair()])));
        $notes = new EncryptedType(new TextType(), $envelopes, 'app');
        $roles = new EncryptedType(new JsonType(), $envelopes, 'app');

        $written = $notes->convertToDatabaseValue('{"role":"admin"}', $this->platform);

        $this->assertSame('{"role":"admin"}', $notes->convertToPHPValue($written, $this->platform), 'the envelope binds its own AAD, so the sealed box never sees one.');

        $this->expectException(DecryptionFailedException::class);
        $roles->convertToPHPValue($written, $this->platform);
    }

    public function testAKeyTooLongForItsLengthPrefixIsRefused()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The key of an encrypted type is at most 65535 bytes long, 65536 given.');

        new EncryptedType(new StringType(), $this->envelopes, str_repeat('a', 65536));
    }

    public function testADecoratingEncrypterAddsItsOwnContextToTheTypes()
    {
        $seen = [];
        $decorated = self::appendingToTheAad(new StoredEnvelopeEncrypter(new InMemoryDataKeyStore()), '/row-42', $seen);
        $type = new EncryptedType(new StringType(), $decorated, 'user.data');

        $written = $type->convertToDatabaseValue('a note', $this->platform);

        $this->assertSame('a note', $type->convertToPHPValue($written, $this->platform));
        $this->assertCount(2, $seen);
        $this->assertSame($seen[0], $seen[1], 'the write and the read must hand the same bytes down.');
        $this->assertStringEndsWith('/row-42', $seen[0]);
        $this->assertStringStartsWith('symfony/key-management/doctrine-dbal/encrypted-type/v1', $seen[0], 'the type context is what the decorator was given to add to.');
    }

    public function testAColumnIsMovedToAnotherKms()
    {
        $target = new EnvelopeEncrypter(new OpenSslKms(new InMemoryKeyLoader(['next' => random_bytes(32)])));
        $legacy = new EncryptedType(new StringType(), $this->envelopes, 'app');
        $written = $legacy->convertToDatabaseValue('jane@example.com', $this->platform);

        $rewriting = new EncryptedType(new StringType(), $this->routingOnKeyId($target), 'next');
        $rewritten = $rewriting->convertToDatabaseValue($legacy->convertToPHPValue($written, $this->platform), $this->platform);

        $this->assertSame('next', Envelope::fromBytes($rewritten)->keyId);
        $this->assertSame('jane@example.com', (new EncryptedType(new StringType(), $target, 'next'))->convertToPHPValue($rewritten, $this->platform));
    }

    public function testAColumnHandedBackAsAStreamIsRead()
    {
        $type = $this->makeType(new StringType());
        $encrypted = $type->convertToDatabaseValue('jane@example.com', $this->platform);

        $this->assertSame('jane@example.com', $type->convertToPHPValue(self::asStream($encrypted), $this->platform));
    }

    #[TestWith([false], 'an empty string')]
    #[TestWith([true], 'an empty stream, as pdo_pgsql hands a BLOB over')]
    public function testAnEmptyColumnIsRejectedInsteadOfGoingToTheParentType(bool $asStream)
    {
        $type = $this->makeType(new StringType());

        $this->expectException(ValueNotConvertible::class);
        $this->expectExceptionMessage('not a valid KeyManagement envelope');
        $type->convertToPHPValue($asStream ? self::asStream('') : '', $this->platform);
    }

    public function testAColumnInAnUnknownFormatIsRejectedLikeAMalformedOne()
    {
        $type = $this->makeType(new StringType());
        $written = $type->convertToDatabaseValue('hello@example.com', $this->platform);

        $this->expectException(ValueNotConvertible::class);
        $this->expectExceptionMessage('not a valid KeyManagement envelope');
        $type->convertToPHPValue(\chr(0x2A).substr((string) $written, 1), $this->platform);
    }

    public function testAnEmptyPlaintextRoundTripsThroughAnEnvelope()
    {
        $type = $this->makeType(new StringType());

        $encrypted = $type->convertToDatabaseValue('', $this->platform);

        $this->assertNotSame('', $encrypted, 'An empty plaintext is framed and authenticated like any other.');
        $this->assertSame('', $type->convertToPHPValue($encrypted, $this->platform));
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

        $this->expectException(ValueNotConvertible::class);
        $this->expectExceptionMessage('not a valid KeyManagement envelope');
        $type->convertToPHPValue("\x01\xff\xff", $this->platform);
    }

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

    private static function appendingToTheAad(EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $inner, string $own, array &$seen): EnvelopeEncrypterInterface&EnvelopeDecrypterInterface
    {
        return new class($inner, $own, $seen) implements EnvelopeEncrypterInterface, EnvelopeDecrypterInterface {
            public function __construct(
                private EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $inner,
                private string $own,
                private array &$seen,
            ) {
            }

            public function encrypt(string $key, #[\SensitiveParameter] string $plaintext, string $aad = ''): Envelope
            {
                $this->seen[] = $aad.$this->own;

                return $this->inner->encrypt($key, $plaintext, $aad.$this->own);
            }

            public function decrypt(Envelope $envelope, string $aad = ''): string
            {
                $this->seen[] = $aad.$this->own;

                return $this->inner->decrypt($envelope, $aad.$this->own);
            }
        };
    }

    private static function asStream(string $value)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $value);
        rewind($stream);

        return $stream;
    }
}
