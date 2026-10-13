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
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
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
 * Both halves are bound to the context the store wraps with,
 * {@see RewrappableDataKeyStoreInterface::wrappingContextOf()}. `--accept-unbound` also accepts a key
 * wrapped under none. This is how a store that starts binding a context moves the rows written
 * before it did. It is the only place where the old shape is accepted, never on a read. Each key is
 * tried under no context first, then under the context of the store, so such a run can be resumed
 * as well. Either way, the key is re-wrapped under the context of the store, never under none.
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

        Turning on the wrapping context of a store leaves the rows written before it unreadable.
        Move them over once with <comment>--accept-unbound</comment>, which also reads the keys wrapped
        under no context. Every key is re-wrapped under the context of the store, never under none:

          <info>php %command.full_name% --to=vault --key-id=app-dek --accept-unbound</info>
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
        #[Option(description: 'Also accept the keys wrapped under no context, written before the store bound one')]
        bool $acceptUnbound = false,
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

        if (null !== $from && !$this->clients?->has($from)) {
            $errorIo->error(\sprintf('Unknown KMS client "%s". Available: %s.', $from, implode(', ', $this->suggestClients())));

            return Command::INVALID;
        }

        $target = self::resolveDataKeyGenerator($this->clients, $to, $errorIo);
        if (\is_int($target)) {
            return $target;
        }

        $moved = 0;
        $failed = 0;

        foreach ([...$this->store->all($from)] as $row) {
            $reference = bin2hex($row->reference);

            try {
                if (!$dryRun) {
                    $this->store->rewrap($row->reference, $this->rewrap($row, $target, $keyId, $this->store->wrappingContextOf($row), $acceptUnbound), $to);
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
     * Unwraps through {@see DataKeyGeneratorInterface::unwrapDataKey()}, not a bare `decrypt()`.
     *
     * That keeps the plaintext inside a {@see DataKey}, which wipes it once re-wrapped.
     *
     * This is the one path that opens a stored key without going through the store, so the binding is
     * checked here too: a row the store refuses to open is reported and left as it is, instead of
     * being re-wrapped into a row the store still refuses.
     */
    private function rewrap(StoredDataKey $row, EncrypterInterface $target, string $keyId, string $context, bool $acceptUnbound): Ciphertext
    {
        $source = $this->clients?->has($row->client) ? $this->clients->get($row->client) : null;

        if (!$source instanceof DataKeyGeneratorInterface) {
            throw new LogicException(\sprintf('the client "%s" that wrapped it is not registered, or cannot unwrap data keys', $row->client));
        }

        try {
            $unwrapped = $source->unwrapDataKey($row->wrapped, $acceptUnbound ? '' : $context);
        } catch (ExceptionInterface $e) {
            if (!$acceptUnbound || '' === $context) {
                throw $e;
            }

            try {
                $unwrapped = $source->unwrapDataKey($row->wrapped, $context);
            } catch (ExceptionInterface $retryFailure) {
                // one of the two attempts is expected to fail on the context, so report the other failure
                throw $e instanceof DecryptionFailedException ? $retryFailure : $e;
            }
        }

        return $unwrapped->use(static function (#[\SensitiveParameter] string $dataKey) use ($row, $target, $keyId, $context): Ciphertext {
            if (!hash_equals($row->binding, StoredDataKey::bindingFor($row->reference, $row->scope, $dataKey))) {
                throw new DecryptionFailedException();
            }

            return $target->encrypt($keyId, $dataKey, $context);
        });
    }
}
