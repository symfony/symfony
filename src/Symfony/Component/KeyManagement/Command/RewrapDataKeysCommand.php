<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\ExceptionInterface;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\KeyManagement\StoredDataKey;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Moves the stored data keys under another master key, or another provider.
 *
 * Each row is unwrapped with the client it records and re-wrapped with the target one, then the row
 * is updated. Encrypted payloads are neither read nor rewritten: they keep referring to the same
 * data keys, which is the whole point of holding those keys in a store.
 *
 * The run is resumable and interruptible. Every row is committed on its own, and a row already
 * moved is simply no longer listed by `--from`, so running the command again picks up what is left.
 *
 * The listing is read in full before the first row is written, rather than consumed as it goes: a
 * store backed by a database hands it over on the same connection the rewrapping writes to, and
 * some drivers refuse a statement while a result set is still open. A data key row is small, and
 * there is one per scope and per rotation, so holding them all costs little.
 *
 * One caveat worth knowing: each data key passes through this process in plaintext, for as long as
 * it takes to re-wrap it. Some providers offer a server-side re-encryption primitive that would
 * avoid it (AWS KMS `ReEncrypt`, for one), which the component does not abstract yet.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
#[AsCommand(
    name: 'key-management:rewrap-data-keys',
    description: 'Re-wrap stored data keys under another master key or provider',
    help: <<<'HELP'
        The <info>%command.name%</info> command re-wraps every stored data key under another master
        key, or under another provider entirely. Payloads are left untouched: they refer to
        data keys, and only the way those keys are protected changes.

          <info>php %command.full_name% --to=azure --key-id=my-vault-key</info>

        Restrict the run to the keys a given client still holds, which is what a provider
        migration looks like while both clients are configured:

          <info>php %command.full_name% --from=aws --to=azure --key-id=my-vault-key</info>

        Rows are committed one by one, so an interrupted run is resumed by running the same
        command again. Start with <comment>--dry-run</comment> to see the scope of the operation.
        HELP,
)]
final class RewrapDataKeysCommand
{
    use CommandTrait;

    /**
     * @param ServiceProviderInterface<EncrypterInterface&DecrypterInterface>|null $clients
     */
    public function __construct(
        private readonly ?RewrappableDataKeyStoreInterface $store = null,
        private readonly ?ServiceProviderInterface $clients = null,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Name of the KMS client that must wrap the keys from now on', suggestedValues: [self::class, 'suggestClients'])]
        ?string $to = null,
        #[Option(name: 'key-id', description: 'Master key on that client (backend-specific: alias, ARN, key URL, ...)')]
        ?string $keyId = null,
        #[Option(description: 'Only move the keys currently wrapped by this client; all of them when omitted', suggestedValues: [self::class, 'suggestClients'])]
        ?string $from = null,
        #[Option(description: 'List what would be moved without writing anything')]
        bool $dryRun = false,
    ): int {
        $errorIo = $io->getErrorStyle();

        if (null === $this->store) {
            $errorIo->error('No data key store is registered, so there is nothing to re-wrap.');

            return Command::FAILURE;
        }

        if (null === $keyId) {
            $errorIo->error('The --key-id option is required: it names the master key that must wrap the data keys from now on.');

            return Command::INVALID;
        }

        if (null === $to) {
            $errorIo->error('The --to option is required: it names the KMS client that must wrap the data keys from now on.');

            return Command::INVALID;
        }

        $target = self::resolveKms($this->clients, $to, $errorIo);
        if (\is_int($target)) {
            return $target;
        }

        $moved = 0;
        $failed = 0;

        foreach ([...$this->store->all($from)] as $row) {
            $reference = bin2hex($row->reference);

            try {
                if (!$dryRun) {
                    $this->store->rewrap($row->reference, $this->rewrap($row, $target, $keyId), $to);
                }
                ++$moved;
                $io->writeln(\sprintf('%s %s (scope "%s") from "%s" to "%s"', $dryRun ? 'Would move' : 'Moved', $reference, $row->scope, $row->client, $to), OutputInterface::VERBOSITY_VERBOSE);
            } catch (ExceptionInterface $e) {
                ++$failed;
                $errorIo->warning(\sprintf('Data key %s (scope "%s") stays with "%s": %s', $reference, $row->scope, $row->client, $e->getMessage()));
            }
        }

        if ($failed) {
            $errorIo->error(\sprintf('%d data key(s) moved to "%s", %d left behind. Fix the reported causes and run the command again.', $moved, $to, $failed));

            return Command::FAILURE;
        }

        $errorIo->success($dryRun
            ? \sprintf('%d data key(s) would move to "%s". Nothing was written.', $moved, $to)
            : \sprintf('%d data key(s) now wrapped by "%s".', $moved, $to));

        return Command::SUCCESS;
    }

    /**
     * Unwrapping through {@see DataKeyGeneratorInterface::unwrapDataKey()} rather than a bare
     * `decrypt()` keeps the plaintext inside a {@see DataKey}, which wipes it once re-wrapped.
     */
    private function rewrap(StoredDataKey $row, EncrypterInterface $target, string $keyId): Ciphertext
    {
        $source = $this->clients?->has($row->client) ? $this->clients->get($row->client) : null;

        if (!$source instanceof DataKeyGeneratorInterface) {
            throw new LogicException(\sprintf('the client "%s" that wrapped it is not registered, or cannot unwrap data keys', $row->client));
        }

        return $source->unwrapDataKey($row->wrapped)->use(static fn (#[\SensitiveParameter] string $dataKey): Ciphertext => $target->encrypt($keyId, $dataKey));
    }
}
