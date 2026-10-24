<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Command;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Command\RewrapDataKeysCommand;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\KeyManagement\StoredDataKey;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\KeyManagement\Tests\Fixtures\EncryptOnlyKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;
use Symfony\Component\KeyManagement\Tests\Fixtures\UnreachableKms;

#[RequiresPhpExtension('openssl')]
class RewrapDataKeysCommandTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private OpenSslKms $aws;
    private OpenSslKms $azure;
    private InMemoryDataKeyStore $store;

    protected function setUp(): void
    {
        $this->aws = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $this->azure = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $this->store = new InMemoryDataKeyStore(['aws' => $this->aws, 'azure' => $this->azure], 'aws');
    }

    public function testEveryKeyMovesToTheTargetClient()
    {
        $this->store->current('user.email');
        $this->store->current('user.phone');

        $tester = $this->tester();
        $tester->execute(['--from' => 'aws', '--to' => 'azure', '--key-id' => 'app']);
        $tester->assertCommandIsSuccessful();

        $this->assertStringContainsString('2 data key(s) now wrapped by "azure"', $tester->getDisplay());
        $this->assertSame(['azure', 'azure'], array_map(static fn (StoredDataKey $row): string => $row->client, iterator_to_array($this->store->all(), false)));
    }

    public function testThePayloadsKeepDecryptingAfterTheMove()
    {
        $encrypter = new StoredEnvelopeEncrypter($this->store);
        $envelope = $encrypter->encrypt('user.email', 'survives the migration');
        $frozen = (string) $envelope;

        $this->tester()->execute(['--from' => 'aws', '--to' => 'azure', '--key-id' => 'app']);
        $this->store->forget();

        $this->assertSame('survives the migration', $encrypter->decrypt($envelope));
        $this->assertSame($frozen, (string) $envelope, 'a payload is never rewritten by the migration.');
    }

    public function testTheRowsOfAContextBindingStoreStayReadableAfterTheMove()
    {
        $store = new InMemoryDataKeyStore(['aws' => $this->aws, 'azure' => $this->azure], 'aws', 'app', 32, null, null, true);
        $handle = $store->current('user.email');
        $plaintext = $handle->use(static fn (string $key): string => $key);

        $tester = new CommandTester(new RewrapDataKeysCommand($store, $this->locator()));
        $tester->execute(['--to' => 'azure', '--key-id' => 'app']);
        $store->forget();

        $tester->assertCommandIsSuccessful();
        $this->assertSame($plaintext, $store->get($handle->reference)->use(static fn (string $key): string => $key), 'a row re-wrapped without the context its store binds is one that store can no longer open.');
    }

    public function testAcceptUnboundMovesTheRowsWrittenBeforeTheStoreBoundAContext()
    {
        $store = new InMemoryDataKeyStore(['aws' => $this->aws, 'azure' => $this->azure], 'aws', 'app', 32, null, null, true);
        $handle = $store->current('user.email');
        $plaintext = $handle->use(static fn (string $key): string => $key);
        // wrapped under no context on purpose: this is how the store wrote it before it bound one
        $store->rewrap(self::rowOf($store, $handle->reference), new DataKey($plaintext, $this->aws->encrypt('app', $plaintext)), 'aws');
        $store->forget();

        $tester = new CommandTester(new RewrapDataKeysCommand($store, $this->locator()));
        $tester->execute(['--to' => 'azure', '--key-id' => 'app', '--accept-unbound' => true]);
        $store->forget();

        $tester->assertCommandIsSuccessful();
        $this->assertSame($plaintext, $store->get($handle->reference)->use(static fn (string $key): string => $key));
    }

    public function testAcceptUnboundCanBeRunAgainOnRowsAlreadyMoved()
    {
        [$store, $plaintexts] = $this->storeHalfwayThroughAnAcceptUnboundRun();

        $tester = new CommandTester(new RewrapDataKeysCommand($store, $this->locator()));
        $tester->execute(['--to' => 'aws', '--key-id' => 'app', '--accept-unbound' => true]);
        $store->forget();

        $tester->assertCommandIsSuccessful();
        foreach ($plaintexts as $reference => $plaintext) {
            $this->assertSame($plaintext, $store->get($reference)->use(static fn (string $key): string => $key));
        }
    }

    public function testAcceptUnboundCanBeRunAgainOnABackendThatCannotTellAContextMismatchFromAnotherFailure()
    {
        [$store, $plaintexts] = $this->storeHalfwayThroughAnAcceptUnboundRun();
        $kmip = $this->awsUnwrappingThrough(static function (OpenSslKms $kms, Ciphertext $wrapped, string $aad): DataKey {
            try {
                return $kms->unwrapDataKey($wrapped, $aad);
            } catch (DecryptionFailedException) {
                throw new RuntimeException('KMIP operation failed (reason 0x4).');
            }
        });

        $tester = new CommandTester(new RewrapDataKeysCommand($store, new ServiceLocator(['aws' => static fn (): DataKeyGeneratorInterface => $kmip, 'azure' => fn (): OpenSslKms => $this->azure])));
        $tester->execute(['--to' => 'azure', '--key-id' => 'app', '--accept-unbound' => true]);
        $store->forget();

        $tester->assertCommandIsSuccessful();
        foreach ($plaintexts as $reference => $plaintext) {
            $this->assertSame($plaintext, $store->get($reference)->use(static fn (string $key): string => $key));
        }
    }

    public function testAcceptUnboundReportsTheFailureThatIsNotTheContextMismatch()
    {
        [$store] = $this->storeHalfwayThroughAnAcceptUnboundRun();
        $throttled = $this->awsUnwrappingThrough(static function (OpenSslKms $kms, Ciphertext $wrapped, string $aad): DataKey {
            $kms->decrypt($wrapped, $aad);

            throw new RuntimeException('The KMS throttled the request.');
        });

        $tester = new CommandTester(new RewrapDataKeysCommand($store, new ServiceLocator(['aws' => static fn (): DataKeyGeneratorInterface => $throttled, 'azure' => fn (): OpenSslKms => $this->azure])));
        $exit = $tester->execute(['--to' => 'azure', '--key-id' => 'app', '--accept-unbound' => true]);

        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertSame(Command::FAILURE, $exit);
        $this->assertSame(2, substr_count($display, 'The KMS throttled the request.'), 'the failure of either shape is reported, not the context mismatch of the other one.');
        $this->assertStringNotContainsString('Decryption failed.', $display);
    }

    public function testARowThatNoLongerMatchesItsKeyIsReportedAndLeftBehind()
    {
        $handle = $this->store->current('user.email');
        $this->store->rewrap(self::rowOf($this->store, $handle->reference), new DataKey($handle->use(static fn (string $key): string => $key), $this->azure->encrypt('app', random_bytes(32))), 'azure');

        $tester = $this->tester();
        $exit = $tester->execute(['--to' => 'aws', '--key-id' => 'app']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('1 left behind', preg_replace('/\s+/', ' ', $tester->getDisplay()));
        $this->assertSame('azure', iterator_to_array($this->store->all(), false)[0]->client, 'a row whose binding no longer matches the key it holds is reported instead of being re-wrapped.');
    }

    public function testTheScopeAndTheReferenceAreLeftAlone()
    {
        $reference = $this->store->current('user.email')->reference;

        $this->tester()->execute(['--to' => 'azure', '--key-id' => 'app']);

        $row = iterator_to_array($this->store->all(), false)[0];
        $this->assertSame($reference, $row->reference);
        $this->assertSame('user.email', $row->scope);
    }

    public function testADryRunWritesNothing()
    {
        $this->store->current('user.email');

        $tester = $this->tester();
        $tester->execute(['--from' => 'aws', '--to' => 'azure', '--key-id' => 'app', '--dry-run' => true]);
        $tester->assertCommandIsSuccessful();

        $this->assertStringContainsString('would move', $tester->getDisplay());
        $this->assertStringContainsString('Nothing was written', $tester->getDisplay());
        $this->assertSame('aws', iterator_to_array($this->store->all(), false)[0]->client);
    }

    public function testTheFromFilterLimitsTheRun()
    {
        $this->store->current('user.email');

        $tester = $this->tester();
        $tester->execute(['--from' => 'azure', '--to' => 'aws', '--key-id' => 'app']);
        $tester->assertCommandIsSuccessful();

        $this->assertStringContainsString('0 data key(s)', $tester->getDisplay());
        $this->assertSame('aws', iterator_to_array($this->store->all(), false)[0]->client);
    }

    public function testARunIsResumable()
    {
        $this->store->current('user.email');
        $this->tester()->execute(['--from' => 'aws', '--to' => 'azure', '--key-id' => 'app']);

        $tester = $this->tester();
        $tester->execute(['--from' => 'aws', '--to' => 'azure', '--key-id' => 'app']);
        $tester->assertCommandIsSuccessful();

        $this->assertStringContainsString('0 data key(s)', $tester->getDisplay(), 'a key already moved is no longer listed by --from.');
    }

    public function testAKeyWhoseClientIsGoneIsReportedAndLeftBehind()
    {
        $this->store = new InMemoryDataKeyStore(['aws' => $this->aws, 'azure' => $this->azure, 'vanished' => $this->aws], 'aws');
        $handle = $this->store->current('user.email');
        $row = self::rowOf($this->store, $handle->reference);
        $this->store->rewrap($row, new DataKey($handle->use(static fn (string $key): string => $key), $row->wrapped), 'vanished');

        $tester = $this->tester();
        $exit = $tester->execute(['--to' => 'azure', '--key-id' => 'app']);

        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('the client "vanished" that wrapped it is not registered', $display);
        $this->assertStringContainsString('1 left behind', $display);
        $this->assertSame('vanished', iterator_to_array($this->store->all(), false)[0]->client, 'a key that cannot be unwrapped must not be touched.');
    }

    public function testTheVerboseOutputNamesEachKey()
    {
        $reference = $this->store->current('user.email')->reference;

        $tester = $this->tester();
        $tester->execute(['--to' => 'azure', '--key-id' => 'app'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertStringContainsString(bin2hex($reference), $tester->getDisplay());
    }

    public function testTheTargetClientIsRequired()
    {
        $tester = $this->tester();
        $exit = $tester->execute(['--key-id' => 'app']);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('--to option is required', $tester->getDisplay());
    }

    public function testTheMasterKeyIsRequired()
    {
        $tester = $this->tester();
        $exit = $tester->execute(['--to' => 'azure']);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('--key-id option is required', $tester->getDisplay());
    }

    public function testAnUnknownTargetClientIsRejected()
    {
        $tester = $this->tester();
        $exit = $tester->execute(['--to' => 'gcp', '--key-id' => 'app']);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('Unknown KMS client "gcp"', $tester->getDisplay());
    }

    public function testAnUnknownSourceClientIsRejected()
    {
        $this->store->current('user.email');

        $tester = $this->tester();
        $exit = $tester->execute(['--from' => 'asw', '--to' => 'azure', '--key-id' => 'app']);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('Unknown KMS client "asw". Available: aws, azure.', preg_replace('/\s+/', ' ', $tester->getDisplay()));
        $this->assertSame('aws', iterator_to_array($this->store->all(), false)[0]->client);
    }

    public function testATargetThatCannotUnwrapDataKeysIsRejected()
    {
        $this->store->current('user.email');
        $clients = new ServiceLocator([
            'aws' => fn (): OpenSslKms => $this->aws,
            'azure' => static fn (): EncryptOnlyKms => new EncryptOnlyKms(),
        ]);

        $tester = new CommandTester(new RewrapDataKeysCommand($this->store, $clients));
        $exit = $tester->execute(['--to' => 'azure', '--key-id' => 'app']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('does not support data-key generation', preg_replace('/\s+/', ' ', $tester->getDisplay()));
        $this->assertSame('aws', iterator_to_array($this->store->all(), false)[0]->client);
    }

    public function testTheDataKeyDoesNotReachStackTraces()
    {
        $dataKey = $this->store->current('user.email')->use(static fn (string $key): string => $key);
        $clients = new ServiceLocator([
            'aws' => fn (): OpenSslKms => $this->aws,
            'azure' => static fn (): UnreachableKms => new UnreachableKms(),
        ]);
        $tester = new CommandTester(new RewrapDataKeysCommand($this->store, $clients));

        $trace = self::traceOf(static fn () => $tester->execute(['--from' => 'aws', '--to' => 'azure', '--key-id' => 'app']));

        self::assertRedacted($dataKey, $trace);
    }

    public function testCompletionSuggestsTheRegisteredClients()
    {
        $tester = new CommandCompletionTester(new Command(null, new RewrapDataKeysCommand($this->store, $this->locator())));

        $this->assertSame(['aws', 'azure'], $tester->complete(['--to', '']));
        $this->assertSame(['aws', 'azure'], $tester->complete(['--from', '']));
    }

    public function testNothingIsWrittenWhileTheListingIsStillOpen()
    {
        $this->store->current('user.email');
        $this->store->current('user.phone');
        $store = new class($this->store) implements RewrappableDataKeyStoreInterface {
            public bool $listing = false;

            public function __construct(private readonly InMemoryDataKeyStore $inner)
            {
            }

            public function all(?string $client = null): iterable
            {
                $this->listing = true;

                try {
                    yield from $this->inner->all($client);
                } finally {
                    $this->listing = false;
                }
            }

            public function rewrap(StoredDataKey $row, DataKey $rewrapped, string $client): void
            {
                if ($this->listing) {
                    throw new \LogicException('The listing was still open.');
                }

                $this->inner->rewrap($row, $rewrapped, $client);
            }

            public function wrappingContextOf(StoredDataKey $row): string
            {
                return $this->inner->wrappingContextOf($row);
            }

            public function current(string $scope): DataKeyHandle
            {
                return $this->inner->current($scope);
            }

            public function get(string $reference): DataKeyHandle
            {
                return $this->inner->get($reference);
            }

            public function rotate(string $scope): DataKeyHandle
            {
                return $this->inner->rotate($scope);
            }
        };

        $tester = new CommandTester(new RewrapDataKeysCommand($store, $this->locator()));
        $tester->execute(['--to' => 'azure', '--key-id' => 'app']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('2 data key(s) now wrapped by "azure"', $tester->getDisplay());
    }

    public function testAMissingStoreIsReported()
    {
        $tester = new CommandTester(new RewrapDataKeysCommand(null, $this->locator()));
        $exit = $tester->execute(['--to' => 'azure', '--key-id' => 'app']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('No data key store is registered', $tester->getDisplay());
    }

    /**
     * @return array{InMemoryDataKeyStore, array<string, string>}
     */
    private function storeHalfwayThroughAnAcceptUnboundRun(): array
    {
        $store = new InMemoryDataKeyStore(['aws' => $this->aws, 'azure' => $this->azure], 'aws', 'app', 32, null, null, true);
        $moved = $store->current('user.email');
        $old = $store->current('user.phone');
        $plaintexts = [$moved->reference => $moved->use(static fn (string $key): string => $key), $old->reference => $old->use(static fn (string $key): string => $key)];
        // wrapped under no context on purpose: this is a row the previous run did not reach yet
        $store->rewrap(self::rowOf($store, $old->reference), new DataKey($plaintexts[$old->reference], $this->aws->encrypt('app', $plaintexts[$old->reference])), 'aws');
        $store->forget();

        return [$store, $plaintexts];
    }

    /**
     * @param \Closure(OpenSslKms, Ciphertext, string): DataKey $unwrap
     */
    private function awsUnwrappingThrough(\Closure $unwrap): DataKeyGeneratorInterface
    {
        return new class($this->aws, $unwrap) implements DataKeyGeneratorInterface {
            public function __construct(
                private readonly OpenSslKms $kms,
                private readonly \Closure $unwrap,
            ) {
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->kms->generateDataKey($keyId, $length, $aad);
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                return ($this->unwrap)($this->kms, $wrapped, $aad);
            }
        };
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new RewrapDataKeysCommand($this->store, $this->locator()));
    }

    private function locator(): ServiceLocator
    {
        return new ServiceLocator([
            'aws' => fn (): OpenSslKms => $this->aws,
            'azure' => fn (): OpenSslKms => $this->azure,
        ]);
    }

    private static function rowOf(InMemoryDataKeyStore $store, string $reference): StoredDataKey
    {
        foreach ($store->all() as $row) {
            if ($reference === $row->reference) {
                return $row;
            }
        }

        throw new \LogicException('No row has that reference.');
    }
}
