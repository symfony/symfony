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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Command\RewrapDataKeysCommand;
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
use Symfony\Component\KeyManagement\RedundantKms;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Test\SwitchableKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\EncryptOnlyKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;
use Symfony\Component\KeyManagement\Tests\Fixtures\UnreachableKms;

class RedundantKmsTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private SwitchableKms $aws;
    private SwitchableKms $azure;
    private SwitchableKms $gcp;

    protected function setUp(): void
    {
        $this->aws = new SwitchableKms(new InMemoryKms(), 'aws is down.');
        $this->azure = new SwitchableKms(new InMemoryKms(), 'azure is down.');
        $this->gcp = new SwitchableKms(new InMemoryKms(), 'gcp is down.');
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
     * A provider lost for good is a member gone from the configuration: what it wrapped is passed
     * over, and the other wrappings still read.
     */
    public function testACiphertextIsReadWithoutTheMemberThatWroteItFirst()
    {
        $ciphertext = $this->kms()->encrypt('app', 'secret');
        $survivor = new RedundantKms(self::locator(['azure' => $this->azure]), 'azure', []);

        $this->assertSame('secret', $survivor->decrypt($ciphertext));
    }

    public function testEachWrappingIsCompleteOnItsOwn()
    {
        $ciphertext = $this->kms()->encrypt('app', 'secret');

        foreach (['aws' => $this->aws, 'azure' => $this->azure, 'gcp' => $this->gcp] as $name => $member) {
            $alone = new RedundantKms(self::locator([$name => $member]), $name, []);
            $this->assertSame('secret', $alone->decrypt($ciphertext), $name);
        }
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
        $stranger = new RedundantKms(self::locator(['vault' => new InMemoryKms()]), 'vault', []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('None of the KMS clients that wrapped the ciphertext ("aws", "azure", "gcp") is registered');
        $stranger->decrypt($ciphertext);
    }

    /**
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
            $alone = new RedundantKms(self::locator([$name => $member]), $name, []);
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
     * The minted key is consumed on the way, so its plaintext is wiped as soon as the redundant
     * one exists, and the redundant one holds a buffer of its own.
     */
    public function testTheMintedDataKeyIsConsumed()
    {
        $minting = self::minting(random_bytes(32));
        $kms = new RedundantKms(self::locator(['aws' => $minting, 'azure' => $this->azure]), 'aws', ['azure' => 'backup']);

        $dataKey = $kms->generateDataKey('app');

        $this->assertTrue($minting->minted->isConsumed());
        $this->assertFalse($dataKey->isConsumed());
        $this->assertSame(32, \strlen($dataKey->use(static fn (string $key): string => $key)));
    }

    /**
     * The plaintext of a data key passes through each recipient while it is wrapped, in a closure
     * whose argument the trace of a failing recipient would otherwise carry.
     */
    public function testTheDataKeyDoesNotReachStackTraces()
    {
        $known = random_bytes(32);
        $kms = new RedundantKms(self::locator(['aws' => self::minting($known), 'azure' => new UnreachableKms()]), 'aws', ['azure' => 'backup']);

        $trace = self::traceOf(static fn () => $kms->generateDataKey('app'));

        self::assertRedacted($known, $trace);
    }

    public function testAMemberThatCannotDecryptIsRefusedToEncrypt()
    {
        $kms = new RedundantKms(self::locator(['aws' => $this->aws, 'azure' => new EncryptOnlyKms()]), 'aws', ['azure' => 'backup']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The KMS client "azure" does not implement "Symfony\Component\KeyManagement\DataKeyGeneratorInterface"');
        $kms->generateDataKey('app');
    }

    public function testAnUnregisteredMemberIsReportedWhenAskedToWrap()
    {
        $kms = new RedundantKms(self::locator(['aws' => $this->aws]), 'aws', ['azure' => 'backup']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No KMS client named "azure" is registered on the redundant client.');
        $kms->encrypt('app', 'secret');
    }

    public function testTheFirstMemberCannotBeARecipientAsWell()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The KMS client "aws" is the one every call names a master key on');

        new RedundantKms(self::locator(['aws' => $this->aws]), 'aws', ['aws' => 'backup']);
    }

    public function testAMemberNameHasToFitInTheCiphertext()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is 256 bytes long');

        new RedundantKms(self::locator(['aws' => $this->aws]), 'aws', [str_repeat('a', 256) => 'backup']);
    }

    public function testAMasterKeyIdHasToFitInTheCiphertext()
    {
        $kms = new RedundantKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure]), 'aws', ['azure' => str_repeat('k', 0x10000)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The master key id of the KMS client "azure" is too long');
        $kms->encrypt('app', 'secret');
    }

    #[DataProvider('provideMalformedBlobs')]
    public function testABlobThatDoesNotParseIsAnUnreadableCiphertext(string $blob)
    {
        $this->expectException(DecryptionFailedException::class);

        $this->kms()->decrypt(new Ciphertext($blob, 'app'));
    }

    public static function provideMalformedBlobs(): iterable
    {
        yield 'empty' => [''];
        yield 'a member blob rather than a redundant one' => [new InMemoryKms()->encrypt('app', 'secret')->blob];
        yield 'unknown version' => ["\x02\x01"];
        yield 'no wrapping' => ["\x01\x00"];
        yield 'truncated name' => ["\x01\x01\x03aw"];
        yield 'empty name' => ["\x01\x01\x00\x00\x03app\x00\x00\x00\x01x"];
        yield 'truncated blob' => ["\x01\x01\x03aws\x00\x03app\x00\x00\x00\x10x"];
        yield 'trailing bytes' => ["\x01\x01\x03aws\x00\x03app\x00\x00\x00\x01x!"];
    }

    #[RequiresPhpExtension('openssl')]
    public function testAnEnvelopeWrittenThroughTheRedundantClientIsReadWithoutItsFirstMember()
    {
        $aws = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $azure = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));
        $encrypter = new EnvelopeEncrypter(new RedundantKms(self::locator(['aws' => $aws, 'azure' => $azure]), 'aws', ['azure' => 'backup']));
        $survivor = new EnvelopeEncrypter(new RedundantKms(self::locator(['azure' => $azure]), 'azure', []));

        $envelope = $encrypter->encrypt('app', 'a payload of any size', 'aad');

        $this->assertSame('a payload of any size', $survivor->decrypt(Envelope::fromBytes((string) $envelope), 'aad'));
    }

    /**
     * The envelope is what the application persists, and one it could not read back through every
     * member is one it must not persist: the write fails as a whole, nothing comes out.
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
     * The recovery the README describes: a member gone for good is replaced in the list, and the
     * data keys a store holds are wrapped under the new list by the rewrap command, from the
     * redundant client to itself. The lost member's wrapping is passed over on the way, and what
     * comes out reads through the newcomer alone.
     */
    #[RequiresPhpExtension('openssl')]
    public function testAStoreIsRewrappedUnderNewMembersThroughTheCommand()
    {
        $aws = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $azure = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));
        $gcp = new OpenSslKms(new InMemoryKeyLoader(['backup' => random_bytes(32)]));

        // the service the store and the command know as "redundant", reconfigured between the two phases
        $redundant = new class(new RedundantKms(self::locator(['aws' => $aws, 'azure' => $azure]), 'aws', ['azure' => 'backup'])) implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface {
            public function __construct(public RedundantKms $members)
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

        $redundant->members = new RedundantKms(self::locator(['azure' => $azure, 'gcp' => $gcp]), 'azure', ['gcp' => 'backup']);
        $tester = new CommandTester(new RewrapDataKeysCommand($store, self::locator(['redundant' => $redundant])));
        $tester->execute(['--from' => 'redundant', '--to' => 'redundant', '--key-id' => 'backup']);
        $tester->assertCommandIsSuccessful();
        $store->forget();

        $redundant->members = new RedundantKms(self::locator(['gcp' => $gcp]), 'gcp', []);
        $this->assertSame('survives the loss of aws', $encrypter->decrypt($envelope));
    }

    private function kms(): RedundantKms
    {
        return new RedundantKms(self::locator(['aws' => $this->aws, 'azure' => $this->azure, 'gcp' => $this->gcp]), 'aws', ['azure' => 'backup', 'gcp' => 'projects/p/keys/backup']);
    }

    /**
     * A member minting `$known` as its data key, and keeping the DataKey it handed out, so that a
     * test knows the plaintext that goes through the redundant client and what became of it.
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
}
