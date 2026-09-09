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
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\KeyManagement\StoredDataKey;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
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
        $reference = $this->store->current('user.email')->reference;
        $this->store->rewrap($reference, iterator_to_array($this->store->all(), false)[0]->wrapped, 'vanished');

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

    /**
     * Each key passes through the process in plaintext for as long as it takes to re-wrap it, in a
     * closure whose argument the trace of a failing target would otherwise carry.
     */
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

    /**
     * The reason the options are declared on the parameters rather than on a mapped input object:
     * only a parameter can carry suggestions that come from the injected clients.
     */
    public function testCompletionSuggestsTheRegisteredClients()
    {
        $tester = new CommandCompletionTester(new Command(null, new RewrapDataKeysCommand($this->store, $this->locator())));

        $this->assertSame(['aws', 'azure'], $tester->complete(['--to', '']));
        $this->assertSame(['aws', 'azure'], $tester->complete(['--from', '']));
    }

    /**
     * A store backed by a database lists its rows on the same connection the rewrapping writes to,
     * and some drivers refuse a statement while a result set is still open.
     */
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

            public function rewrap(string $reference, Ciphertext $wrapped, string $client): void
            {
                if ($this->listing) {
                    throw new \LogicException('The listing was still open.');
                }

                $this->inner->rewrap($reference, $wrapped, $client);
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
}
