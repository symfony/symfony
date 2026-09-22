<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Command\RewrapDataKeysCommand;
use Symfony\Component\KeyManagement\CompositeKms;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Test\SwitchableKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\EncryptOnlyKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;
use Symfony\Component\KeyManagement\Tests\Fixtures\UnreachableKms;

class CompositeKmsTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private SwitchableKms $aws;
    private SwitchableKms $azure;
    private SwitchableKms $gcp;

    protected function setUp(): void
    {
        $this->aws = new SwitchableKms(new InMemoryKms('aws'), new \RuntimeException('aws is down.'));
        $this->azure = new SwitchableKms(new InMemoryKms('azure'), new \RuntimeException('azure is down.'));
        $this->gcp = new SwitchableKms(new InMemoryKms('gcp'), new \RuntimeException('gcp is down.'));
    }

    public function testACiphertextRoundTrips()
    {
        $kms = $this->kms();

        $ciphertext = $kms->encrypt('app', 'secret', 'aad');

        $this->assertSame('app', $ciphertext->keyId, 'the key id is the one the caller named, on the first member.');
        $this->assertSame('secret', $kms->decrypt($ciphertext, 'aad'));
    }

    public function testEveryMemberWrapsAndTheFirstOneIsAskedToRead()
    {
        $kms = $this->kms();

        $kms->decrypt($kms->encrypt('app', 'secret'));

        $this->assertSame(['encrypt' => 1, 'decrypt' => 1], $this->aws->calls);
        $this->assertSame(['encrypt' => 1], $this->azure->calls);
        $this->assertSame(['encrypt' => 1], $this->gcp->calls);
    }

    /**
     * What the redundancy is for: a member that no longer answers is invisible on the read path.
     */
    public function testACiphertextIsReadThroughTheNextMemberWhenTheFirstIsDown()
    {
        $kms = $this->kms();
        $ciphertext = $kms->encrypt('app', 'secret');

        $this->aws->down = true;
        $this->azure->down = true;

        $this->assertSame('secret', $kms->decrypt($ciphertext));
        $this->assertSame(['encrypt' => 1, 'decrypt' => 1], $this->azure->calls, 'the recipients are asked in their configured order.');
        $this->assertSame(['encrypt' => 1, 'decrypt' => 1], $this->gcp->calls);
    }

    /**
     * A provider lost for good is a member replaced in the configuration.
     *
     * What it wrapped is passed over, the newcomer has nothing to read yet, and the other wrappings
     * still read.
     */
    public function testACiphertextIsReadWithoutTheMemberThatWroteItFirst()
    {
        $ciphertext = $this->kms()->encrypt('app', 'secret');
        $survivor = new CompositeKms(self::locator(['azure' => $this->azure, 'vault' => new InMemoryKms('vault')]), ['azure' => null, 'vault' => null]);

        $this->assertSame('secret', $survivor->decrypt($ciphertext));
    }

    public function testEachWrappingIsCompleteOnItsOwn()
    {
        $ciphertext = $this->kms()->encrypt('app', 'secret');

        foreach (['aws' => $this->aws, 'azure' => $this->azure, 'gcp' => $this->gcp] as $name => $member) {
            $alone = new CompositeKms(self::locator([$name => $member, 'vault' => new InMemoryKms('vault')]), [$name => null, 'vault' => null]);
            $this->assertSame('secret', $alone->decrypt($ciphertext), $name);
        }
    }

    /**
     * The logger is the only place a member passed over shows.
     *
     * It is a provider in trouble that the caller never hears of, since another one answered.
     */
    public function testAMemberPassedOverIsLogged()
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [$level, $message, $context['client'], $context['exception']->getMessage()];
            }
        };
        $kms = new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure]), ['aws' => null, 'azure' => 'backup'], $logger);
        $ciphertext = $kms->encrypt('app', 'secret');

        $this->aws->down = true;
        $kms->decrypt($ciphertext);

        $this->assertSame([['warning', 'The KMS client "{client}" failed to read a wrapping, the next member is asked.', 'aws', 'aws is down.']], $logger->records);
    }

    public function testTheFirstFailureIsReportedWhenNoMemberAnswers()
    {
        $kms = $this->kms();
        $ciphertext = $kms->encrypt('app', 'secret');

        $this->aws->down = true;
        $this->azure->down = true;
        $this->gcp->down = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('aws is down.');
        $kms->decrypt($ciphertext);
    }

    public function testACiphertextNoRegisteredMemberWroteIsReportedLoudly()
    {
        $ciphertext = $this->kms()->encrypt('app', 'secret');
        $stranger = new CompositeKms(self::locator(['vault' => new InMemoryKms('vault'), 'local' => new InMemoryKms('local')]), ['vault' => null, 'local' => null]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('None of the KMS clients that wrapped the ciphertext ("aws", "azure", "gcp") is registered');
        $stranger->decrypt($ciphertext);
    }

    /**
     * A member that cannot wrap fails the whole write.
     *
     * A ciphertext missing one wrapping is less redundant than the configuration claims, so no
     * ciphertext comes out at all.
     */
    public function testAMemberThatCannotWrapFailsTheWrite()
    {
        $kms = $this->kms();
        $this->gcp->down = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('gcp is down.');
        $kms->encrypt('app', 'secret');
    }

    public function testTheAadBindsEveryWrapping()
    {
        $kms = $this->kms();
        $ciphertext = $kms->encrypt('app', 'secret', 'aad');

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt($ciphertext, 'other');
    }

    public function testTheDeterministicFlagReachesEveryMember()
    {
        $this->kms()->encrypt('app', 'secret', deterministic: true);

        $this->assertSame([true], $this->aws->deterministic);
        $this->assertSame([true], $this->azure->deterministic);
        $this->assertSame([true], $this->gcp->deterministic);
    }

    public function testADataKeyIsWrappedByEveryMemberAndUnwrappedByAny()
    {
        $kms = $this->kms();

        $dataKey = $kms->generateDataKey('app', 32, 'aad');
        $plaintext = $dataKey->use(static fn (string $key): string => $key);

        $this->assertSame(32, \strlen($plaintext));
        $this->assertSame('app', $dataKey->wrapped->keyId);
        $this->assertSame(['generateDataKey' => 1], $this->aws->calls, 'the first member mints the key.');
        $this->assertSame(['encrypt' => 1], $this->azure->calls, 'the others wrap its plaintext.');

        foreach (['aws' => $this->aws, 'azure' => $this->azure, 'gcp' => $this->gcp] as $name => $member) {
            $alone = new CompositeKms(self::locator([$name => $member, 'vault' => new InMemoryKms('vault')]), [$name => null, 'vault' => null]);
            $this->assertSame($plaintext, $alone->unwrapDataKey($dataKey->wrapped, 'aad')->use(static fn (string $key): string => $key), $name);
        }
    }

    public function testADataKeyIsUnwrappedThroughTheNextMemberWhenTheFirstIsDown()
    {
        $kms = $this->kms();
        $dataKey = $kms->generateDataKey('app');
        $plaintext = $dataKey->use(static fn (string $key): string => $key);

        $this->aws->down = true;

        $this->assertSame($plaintext, $kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key));
    }

    /**
     * The minted key is consumed on the way.
     *
     * It gives its plaintext up to the composite one rather than sharing it, so there is one key
     * left to wipe.
     */
    public function testTheMintedDataKeyIsConsumed()
    {
        $minting = self::minting(random_bytes(32));
        $kms = new CompositeKms(self::locator(['aws' => $minting, 'azure' => $this->azure]), ['aws' => null, 'azure' => 'backup']);

        $dataKey = $kms->generateDataKey('app');

        $this->assertTrue($minting->minted->isConsumed());
        $this->assertFalse($dataKey->isConsumed());
        $this->assertSame(32, \strlen($dataKey->use(static fn (string $key): string => $key)));
    }

    /**
     * The trace of a failing recipient does not carry the plaintext it was wrapping.
     *
     * The plaintext passes through each recipient in a closure, whose argument the trace would
     * otherwise show.
     */
    public function testTheDataKeyDoesNotReachStackTraces()
    {
        $known = random_bytes(32);
        $kms = new CompositeKms(self::locator(['aws' => self::minting($known), 'azure' => new UnreachableKms()]), ['aws' => null, 'azure' => 'backup']);

        $trace = self::traceOf(static fn () => $kms->generateDataKey('app'));

        self::assertRedacted($known, $trace);
    }

    /**
     * A member is a full KMS client or no member at all, settled before anything is written.
     *
     * One that could wrap but not read back would be a wrapping nothing reads.
     */
    public function testAMemberThatCannotReadBackIsRefused()
    {
        $kms = new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => new EncryptOnlyKms()]), ['aws' => null, 'azure' => 'backup']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The KMS client "azure" cannot be a member of a composite client');
        $kms->encrypt('app', 'secret');
    }

    public function testAnUnregisteredMemberIsReportedWhenAskedToWrap()
    {
        $kms = new CompositeKms(self::locator(['aws' => $this->aws]), ['aws' => null, 'azure' => 'backup']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No KMS client named "azure" is registered on the composite client.');
        $kms->encrypt('app', 'secret');
    }

    public function testAtLeastOneMemberIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A composite KMS client needs at least one member.');

        new CompositeKms(self::locator(['aws' => $this->aws]), []);
    }

    /**
     * A composite of one is a degraded mode, not a misconfiguration.
     *
     * This is what losing a provider for good looks like, end to end: it is dropped from the
     * members, one is left, and that one keeps reading everything the pair wrapped and keeps
     * writing, until a replacement joins and reads what was written meanwhile. Refusing a single
     * member would leave an application whose two-member pair lost one with nothing to run on,
     * since a plain client does not read a frame either.
     */
    public function testAProviderLostForGoodLeavesTheSurvivorWorkingAlone()
    {
        $pair = new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure]), ['aws' => null, 'azure' => null]);
        $written = $pair->encrypt('app', 'written while both were alive');

        // "aws" is gone for good, so it leaves the members rather than failing every write
        $degraded = new CompositeKms(self::locator(['azure' => $this->azure]), ['azure' => null]);
        $meanwhile = $degraded->encrypt('app', 'written while degraded');

        $this->assertSame('written while both were alive', $degraded->decrypt($written));
        $this->assertSame('written while degraded', $degraded->decrypt($meanwhile));

        $restored = new CompositeKms(self::locator(['azure' => $this->azure, 'gcp' => $this->gcp]), ['azure' => null, 'gcp' => null]);

        $this->assertSame('written while both were alive', $restored->decrypt($written));
        $this->assertSame('written while degraded', $restored->decrypt($meanwhile));
    }

    /**
     * The survivor stays wrapped in a composite.
     *
     * A member on its own is handed a frame it knows nothing about.
     */
    public function testAPlainMemberDoesNotReadAFrame()
    {
        $ciphertext = $this->kms()->encrypt('app', 'secret');

        $this->expectException(DecryptionFailedException::class);
        $this->aws->decrypt($ciphertext);
    }

    /**
     * A member given no master key wraps under the one each call names.
     *
     * This is what lets two providers sharing a key name run with no configuration beyond their
     * names.
     */
    public function testAMemberWithoutAMasterKeyUsesTheOneEachCallNames()
    {
        $kms = new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure]), ['aws' => null, 'azure' => null]);

        $ciphertext = $kms->encrypt('app', 'secret');
        $survivor = new CompositeKms(self::locator(['azure' => $this->azure, 'gcp' => $this->gcp]), ['azure' => null, 'gcp' => null]);

        $this->assertSame('secret', $survivor->decrypt($ciphertext));
        $this->assertSame('app', $this->azure->keyIds[0]);
    }

    public function testAMemberNameHasToFitInTheCiphertext()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is 256 bytes long');

        new CompositeKms(self::locator(['aws' => $this->aws]), ['aws' => null, str_repeat('a', 256) => 'backup']);
    }

    public function testAMasterKeyIdHasToFitInTheCiphertext()
    {
        $kms = new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure]), ['aws' => null, 'azure' => str_repeat('k', 0x10000)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The master key id of the KMS client "azure" is too long');
        $kms->encrypt('app', 'secret');
    }

    /**
     * A blob that is not a frame is handed to the members as it is.
     *
     * What they make of it is what comes back: an unreadable ciphertext, reported by the first
     * member.
     */
    #[DataProvider('provideMalformedBlobs')]
    public function testABlobThatDoesNotParseIsAnUnreadableCiphertext(string $blob)
    {
        $this->expectException(DecryptionFailedException::class);

        $this->kms()->decrypt(new Ciphertext($blob, 'app'));
    }

    public static function provideMalformedBlobs(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown version' => ["\x02\x01"];
        yield 'no wrapping' => ["\x01\x00"];
        yield 'truncated name' => ["\x01\x01\x03aw"];
        yield 'empty name' => ["\x01\x01\x00\x00\x03app\x00\x00\x00\x01x"];
        yield 'truncated blob' => ["\x01\x01\x03aws\x00\x03app\x00\x00\x00\x10x"];
        yield 'trailing bytes' => ["\x01\x01\x03aws\x00\x03app\x00\x00\x00\x01x!"];
    }

    #[RequiresPhpExtension('openssl')]
    public function testAnEnvelopeWrittenThroughTheCompositeClientIsReadWithoutItsFirstMember()
    {
        $aws = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $azure = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));
        $encrypter = new EnvelopeEncrypter(new CompositeKms(self::locator(['aws' => $aws, 'azure' => $azure]), ['aws' => null, 'azure' => 'backup']));
        $vault = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));
        $survivor = new EnvelopeEncrypter(new CompositeKms(self::locator(['azure' => $azure, 'vault' => $vault]), ['azure' => null, 'vault' => null]));

        $envelope = $encrypter->encrypt('app', 'a payload of any size', 'aad');

        $this->assertSame('a payload of any size', $survivor->decrypt(Envelope::fromBytes((string) $envelope), 'aad'));
    }

    /**
     * An envelope the application could not read back through every member is never produced.
     *
     * The envelope is what the application persists, so the write fails as a whole and nothing
     * comes out.
     */
    #[RequiresPhpExtension('openssl')]
    public function testNoEnvelopeIsWrittenWhileAMemberIsDown()
    {
        $encrypter = new EnvelopeEncrypter($this->kms());
        $this->azure->down = true;

        try {
            $encrypter->encrypt('app', 'a payload');
            $this->fail('No envelope must be written.');
        } catch (\RuntimeException $e) {
            $this->assertSame('azure is down.', $e->getMessage());
        }

        $this->assertSame(['generateDataKey' => 1], $this->aws->calls);
        $this->assertSame([], $this->gcp->calls, 'the members after the one that failed are not asked either.');
    }

    /**
     * The recovery the README describes, through the rewrap command.
     *
     * A member gone for good is replaced in the list, and the data keys a store holds are wrapped
     * under the new list by the rewrap command, from the composite client to itself. The lost
     * member's wrapping is passed over on the way, and what comes out reads through the newcomer
     * alone.
     */
    #[RequiresPhpExtension('openssl')]
    public function testAStoreIsRewrappedUnderNewMembersThroughTheCommand()
    {
        $aws = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $azure = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));
        $gcp = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));

        // the service the store and the command know as "redundant", reconfigured between the two phases
        $redundant = new class(new CompositeKms(self::locator(['aws' => $aws, 'azure' => $azure]), ['aws' => null, 'azure' => 'backup'])) implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface {
            public function __construct(public CompositeKms $members)
            {
            }

            public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
            {
                return $this->members->encrypt($keyId, $plaintext, $aad, $deterministic);
            }

            public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
            {
                return $this->members->decrypt($ciphertext, $aad);
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->members->generateDataKey($keyId, $length, $aad);
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                return $this->members->unwrapDataKey($wrapped, $aad);
            }
        };
        $store = new InMemoryDataKeyStore(['redundant' => $redundant], 'redundant', 'app');
        $encrypter = new StoredEnvelopeEncrypter($store);
        $envelope = $encrypter->encrypt('user.email', 'survives the loss of aws');
        $store->forget();

        $redundant->members = new CompositeKms(self::locator(['azure' => $azure, 'gcp' => $gcp]), ['azure' => null, 'gcp' => 'backup']);
        $tester = new CommandTester(new RewrapDataKeysCommand($store, self::locator(['redundant' => $redundant])));
        $tester->execute(['--from' => 'redundant', '--to' => 'redundant', '--key-id' => 'backup']);
        $tester->assertCommandIsSuccessful();
        $store->forget();

        $redundant->members = new CompositeKms(self::locator(['gcp' => $gcp, 'vault' => new InMemoryKms('vault')]), ['gcp' => null, 'vault' => null]);
        $this->assertSame('survives the loss of aws', $encrypter->decrypt($envelope));
    }

    /**
     * What an application wrote before switching to a composite client stays readable.
     *
     * It was written by one member alone, so an unframed ciphertext is handed to each member in
     * turn.
     */
    public function testACiphertextAMemberWroteOnItsOwnIsRead()
    {
        $ciphertext = $this->azure->encrypt('backup', 'written before the switch', 'aad');
        $dataKey = $this->azure->generateDataKey('backup', 32, 'aad');
        $plaintext = $dataKey->use(static fn (string $key): string => $key);
        $kms = $this->kms();

        $this->assertSame('written before the switch', $kms->decrypt($ciphertext, 'aad'));
        $this->assertSame($plaintext, $kms->unwrapDataKey($dataKey->wrapped, 'aad')->use(static fn (string $key): string => $key));
        $this->assertSame(['encrypt' => 1, 'generateDataKey' => 1, 'decrypt' => 1, 'unwrapDataKey' => 1], $this->azure->calls);
        $this->assertSame(['decrypt' => 1, 'unwrapDataKey' => 1], $this->aws->calls, 'the members before the one that wrote it are asked first, and fail.');
        $this->assertSame([], $this->gcp->calls);
    }

    /**
     * A wrapping is read back by the operation that wrote it.
     *
     * On Azure Key Vault, wrapping a key and encrypting a payload are two operations with their own
     * permissions and algorithms, so a wrapping written by encrypt() cannot be read by
     * unwrapDataKey(). Only the minter's wrapping came out of generateDataKey(); the others must
     * be read by decrypt().
     */
    public function testAWrappingIsReadBackByTheOperationThatWroteIt()
    {
        $aws = new SwitchableKms(self::twoOperations(new InMemoryKms('aws')));
        $azure = new SwitchableKms(self::twoOperations(new InMemoryKms('azure')));
        $kms = new CompositeKms(self::locator(['aws' => $aws, 'azure' => $azure]), ['aws' => null, 'azure' => 'backup']);

        $dataKey = $kms->generateDataKey('app');
        $plaintext = $dataKey->use(static fn (string $key): string => $key);

        $this->assertSame($plaintext, $kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key));
        $this->assertSame(['generateDataKey' => 1, 'unwrapDataKey' => 1], $aws->calls, 'the minter reads its wrapping back the way it wrote it.');

        $aws->down = true;

        $this->assertSame($plaintext, $kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key));
        $this->assertSame(['encrypt' => 1, 'decrypt' => 1], $azure->calls, 'the other members read their wrapping back with decrypt(), which is what wrote it.');
    }

    public function testTheDataKeyReadThroughAnyMemberRefersToTheCompositeCiphertext()
    {
        $kms = $this->kms();
        $dataKey = $kms->generateDataKey('app');

        $this->assertSame($dataKey->wrapped, $kms->unwrapDataKey($dataKey->wrapped)->wrapped);

        $this->aws->down = true;

        $this->assertSame($dataKey->wrapped, $kms->unwrapDataKey($dataKey->wrapped)->wrapped);
    }

    /**
     * A client whose two operations are told apart, the way Azure Key Vault tells them apart.
     *
     * A blob is read back by the operation that produced it, "encrypt" or "wrapKey", and by no
     * other.
     */
    private static function twoOperations(InMemoryKms $inner): DataKeyGeneratorInterface&DecrypterInterface&EncrypterInterface
    {
        return new class($inner) implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface {
            public function __construct(private readonly InMemoryKms $inner)
            {
            }

            public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
            {
                return new Ciphertext('encrypt:'.$this->inner->encrypt($keyId, $plaintext, $aad, $deterministic)->blob, $keyId);
            }

            public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
            {
                return $this->inner->decrypt($this->strip('encrypt:', $ciphertext), $aad);
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->inner->generateDataKey($keyId, $length, $aad)->use(fn (#[\SensitiveParameter] string $plaintext): DataKey => new DataKey($plaintext, new Ciphertext('wrap:'.$this->inner->encrypt($keyId, $plaintext, $aad)->blob, $keyId)));
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                return $this->inner->unwrapDataKey($this->strip('wrap:', $wrapped), $aad);
            }

            private function strip(string $operation, Ciphertext $ciphertext): Ciphertext
            {
                if (!str_starts_with($ciphertext->blob, $operation)) {
                    throw new DecryptionFailedException();
                }

                return new Ciphertext(substr($ciphertext->blob, \strlen($operation)), $ciphertext->keyId);
            }
        };
    }

    private function kms(): CompositeKms
    {
        return new CompositeKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure, 'gcp' => $this->gcp]), ['aws' => null, 'azure' => 'backup', 'gcp' => 'projects/p/keys/backup']);
    }

    /**
     * A member minting `$known` as its data key, and keeping the DataKey it handed out.
     *
     * A test then knows the plaintext that goes through the composite client and what became of
     * it.
     */
    private static function minting(#[\SensitiveParameter] string $known): DataKeyGeneratorInterface&DecrypterInterface&EncrypterInterface
    {
        return new class($known) implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface {
            public ?DataKey $minted = null;
            private readonly InMemoryKms $inner;

            public function __construct(#[\SensitiveParameter] private readonly string $known)
            {
                $this->inner = new InMemoryKms();
            }

            public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
            {
                return $this->inner->encrypt($keyId, $plaintext, $aad, $deterministic);
            }

            public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
            {
                return $this->inner->decrypt($ciphertext, $aad);
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->minted = new DataKey($this->known, $this->inner->encrypt($keyId, $this->known, $aad));
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                return $this->inner->unwrapDataKey($wrapped, $aad);
            }
        };
    }

    /**
     * @param array<string, object> $clients
     */
    private static function locator(array $clients): ServiceLocator
    {
        $factories = [];
        foreach ($clients as $name => $client) {
            $factories[$name] = static fn (): object => $client;
        }

        return new ServiceLocator($factories);
    }

    /**
     * A member is passed over whatever its backend throws.
     *
     * A backend is down whatever its SDK throws, and the exception classes an SDK picks are its
     * own, so any of them is passed over rather than the RuntimeException family alone.
     */
    public function testAMemberIsPassedOverWhateverItThrows()
    {
        foreach ([new \DomainException('down.'), new \Exception('down.'), new \InvalidArgumentException('down.')] as $failure) {
            $first = new SwitchableKms(new InMemoryKms(), $failure);
            $kms = new CompositeKms(self::locator(['aws' => $first, 'azure' => new InMemoryKms()]), ['aws' => null, 'azure' => null]);
            $ciphertext = $kms->encrypt('app', 'secret');
            $first->down = true;

            $this->assertSame('secret', $kms->decrypt($ciphertext), $failure::class);
        }
    }

    /**
     * The member count is bounded by the one byte of the blob that records it.
     *
     * A longer list would write ciphertexts no member could ever read back.
     */
    public function testTheNumberOfMembersHasToFitInTheCiphertext()
    {
        $members = [];
        for ($i = 0; $i <= 0xFF; ++$i) {
            $members['m'.$i] = null;
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A composite KMS client has at most 255 members, 256 given.');

        new CompositeKms(self::locator([]), $members);
    }
}
