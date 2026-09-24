<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\KeyManagement\Debug\TraceableKms;
use Symfony\Component\KeyManagement\Exception\CompositeKmsReentryException;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\LogicException;

/**
 * A KMS client made of several, so that losing one of them loses nothing.
 *
 * Every ciphertext it produces is produced by each of its members in turn, and the blob it hands
 * back carries all of those wrappings; reading it back asks the members one after the other and
 * settles for the first that answers. The application sees one client: the same `encrypt()`,
 * `decrypt()`, `generateDataKey()` and `unwrapDataKey()` whether one, two or three providers
 * stand behind it, and every envelope, every stored data key and every direct ciphertext is
 * complete on its own. An outage of one provider is then invisible on the read path, and the
 * loss of one for good leaves ciphertexts readable through the surviving members. Stored data
 * keys must be rewrapped under a replacement member to restore their original redundancy.
 *
 * The members are listed with the master key each of them wraps under, or null for the key id
 * given to each call; the first member is the one that mints data keys and the first one asked to
 * read. Each member is a full KMS client, required to read back what it wraps, and a full path to
 * the plaintext: the list is worth keeping to providers one would otherwise lose sleep over
 * losing, and each of them deserves the protection the first one gets. Composite members are not
 * supported.
 *
 * Writing goes through every member, and a member that cannot wrap fails the whole call: a
 * ciphertext missing one wrapping is a ciphertext less redundant than the configuration claims,
 * and the point of this client is that the claim holds. Reading only needs one. When eligible
 * members are tried and all fail, their first failure is reported; a frame with no eligible
 * wrapping fails decryption.
 *
 * The blob is laid out as `[0x01][count]` followed, for each wrapping, by the member's name, the
 * master key it used and its own blob, each prefixed with its length on one, two and four bytes.
 * The name is what lets a wrapping find its configured member again, as the client column of a data key store does.
 * A former member can be listed among the retired members to keep its old wrappings readable
 * after it stops participating in writes. Without that listing, renaming or removing a member
 * skips wrappings under its old name; another configured member can still read a frame it also wrapped.
 * A retired member remains a full path to the plaintext and needs the same protection as an active one.
 * A member added later is found only in ciphertexts written from then on.
 *
 * A ciphertext that is not such a frame is one a member wrote on its own, before it joined: it is
 * handed as it is to each member in turn, so that switching an application to a composite client
 * leaves what was written before readable.
 *
 * One member is allowed, and is what a provider lost for good leaves behind: dropping it from the
 * members keeps every frame it wrapped readable through the survivor, and what the survivor writes
 * alone is read again by the members that join later. A member that should stop wrapping but
 * still needs to read old ciphertexts belongs among the retired members. The survivor is kept
 * as a composite of one rather than used on its own, since a plain client does not read a frame.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class CompositeKms implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface
{
    private const int VERSION = 1;

    private bool $runningMain = false;

    /** @var \WeakMap<\Fiber, true> */
    private \WeakMap $runningFibers;

    /**
     * @param ContainerInterface         $clients KMS clients, indexed by name
     * @param array<string, string|null> $members Master key each member wraps under, indexed by member name, null for the key id given to each call; the first member mints the data keys and is asked first to read
     * @param LoggerInterface|null       $logger  Told of every member that fails to read while another one answers, since nothing else says so
     * @param list<string>               $retired Former member names accepted for reads but never used for writes
     */
    public function __construct(
        private readonly ContainerInterface $clients,
        private readonly array $members,
        private readonly ?LoggerInterface $logger = null,
        private readonly array $retired = [],
    ) {
        $this->runningFibers = new \WeakMap();

        if (!$members) {
            throw new InvalidArgumentException('A composite KMS client needs at least one member.');
        }

        if (0xFF < \count($members)) {
            throw new InvalidArgumentException(\sprintf('A composite KMS client has at most %d members, %d given.', 0xFF, \count($members)));
        }

        foreach (array_keys($members) as $name) {
            if ('' === $name || 0xFF < \strlen($name)) {
                throw new InvalidArgumentException(\sprintf('A KMS client name is between 1 and 255 bytes long to be recorded in a ciphertext, "%s" is %d bytes long.', $name, \strlen($name)));
            }
        }

        if (!array_is_list($retired)) {
            throw new InvalidArgumentException('Retired KMS clients must be listed by name.');
        }

        $seen = [];
        foreach ($retired as $name) {
            if (!\is_string($name) || '' === $name || 0xFF < \strlen($name)) {
                throw new InvalidArgumentException('A retired KMS client name must be a string between 1 and 255 bytes long.');
            }

            if (\array_key_exists($name, $members)) {
                throw new InvalidArgumentException(\sprintf('The KMS client "%s" cannot be both an active and a retired member.', $name));
            }

            if (isset($seen[$name])) {
                throw new InvalidArgumentException(\sprintf('The retired KMS client "%s" is listed more than once.', $name));
            }

            $seen[$name] = true;
        }
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        $context = $this->enterOperation();
        try {
            $wrappings = [];
            foreach ($this->members as $name => $memberKeyId) {
                $wrappings[$name] = $this->member($name)->encrypt($memberKeyId ?? $keyId, $plaintext, $aad, $deterministic);
            }

            return new Ciphertext(self::frame($wrappings), $keyId);
        } finally {
            $this->leaveOperation($context);
        }
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        return $this->readThroughAny($ciphertext, static fn (DecrypterInterface $member, Ciphertext $wrapping): string => $member->decrypt($wrapping, $aad));
    }

    /**
     * The first member mints the data key and the others wrap its plaintext while it is around.
     *
     * The wrapping happens inside the closure {@see DataKey::use()} hands the plaintext to. The
     * DataKey returned holds that very plaintext: the minted DataKey gives it up as the closure
     * returns, so there is one buffer, owned by the returned key and wiped when it is consumed.
     */
    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        $context = $this->enterOperation();
        try {
            $first = array_key_first($this->members);
            $dataKey = $this->member($first)->generateDataKey($this->members[$first] ?? $keyId, $length, $aad);

            return $dataKey->use(function (#[\SensitiveParameter] string $plaintext) use ($dataKey, $first, $keyId, $aad): DataKey {
                $wrappings = [$first => $dataKey->wrapped];
                foreach ($this->members as $name => $memberKeyId) {
                    if ($name !== $first) {
                        $wrappings[$name] = $this->member($name)->encrypt($memberKeyId ?? $keyId, $plaintext, $aad);
                    }
                }

                return new DataKey($plaintext, new Ciphertext(self::frame($wrappings), $keyId));
            });
        } finally {
            $this->leaveOperation($context);
        }
    }

    /**
     * Each wrapping is read back by the operation that wrote it.
     *
     * Only the first wrapping of the frame came out of `generateDataKey()`, the others out of
     * `encrypt()` on the minted plaintext: on a backend where wrapping a key and encrypting a
     * payload are two operations, Azure Key Vault for one, they answer to different permissions
     * and algorithms. The DataKey handed back refers to the composite ciphertext, whichever
     * member read it.
     */
    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return $this->readThroughAny($wrapped, static function (DataKeyGeneratorInterface&DecrypterInterface $member, Ciphertext $wrapping, bool $minted) use ($wrapped, $aad): DataKey {
            if (!$minted) {
                return new DataKey($member->decrypt($wrapping, $aad), $wrapped);
            }

            return $member->unwrapDataKey($wrapping, $aad)->use(static fn (#[\SensitiveParameter] string $plaintext): DataKey => new DataKey($plaintext, $wrapped));
        });
    }

    /**
     * Asks the members in turn and settles for the first that answers.
     *
     * Active members are asked in order, followed by retired members. A wrapping from a client
     * outside those lists is passed over, as is one from a client that is not registered anymore.
     * A member that fails is passed over too, with a word to the logger since the caller will
     * never hear of it, and its failure is the one reported when none of them answers.
     * A recursive call to a composite stops the read even when another member could answer.
     *
     * A ciphertext that is not a composite frame is handed whole to every member, each treating
     * it as its own, as the class docblock says.
     *
     * @template T
     *
     * @param \Closure(DataKeyGeneratorInterface&DecrypterInterface&EncrypterInterface, Ciphertext, bool): T $read Given the member, its wrapping, and whether the wrapping is the one the member minted
     *
     * @return T
     */
    private function readThroughAny(Ciphertext $ciphertext, \Closure $read): mixed
    {
        $context = $this->enterOperation();
        try {
            $readers = $this->members + array_fill_keys($this->retired, null);
            if (null === $wrappings = self::parse($ciphertext)) {
                $wrappings = array_fill_keys(array_keys($readers), $ciphertext);
                $minter = null;
            } else {
                $minter = array_key_first($wrappings);
                $wrappings = array_intersect_key($wrappings, $readers);

                if (!$wrappings) {
                    throw new DecryptionFailedException();
                }
            }

            $rank = array_flip(array_keys($readers));
            uksort($wrappings, static fn (string $a, string $b): int => ($rank[$a] ?? \PHP_INT_MAX) <=> ($rank[$b] ?? \PHP_INT_MAX));

            $failure = null;
            $unregistered = [];
            foreach ($wrappings as $name => $wrapping) {
                if (!$this->clients->has($name)) {
                    $unregistered[] = $name;
                    continue;
                }

                $member = $this->member($name);

                try {
                    return $read($member, $wrapping, null === $minter || $name === $minter);
                } catch (\Exception $e) {
                    if ($e instanceof CompositeKmsReentryException) {
                        throw $e;
                    }

                    $this->logger?->warning('The KMS client "{client}" failed to read a wrapping, the next member is asked.', ['client' => $name, 'exception' => $e]);
                    $failure ??= $e;
                }
            }

            throw $failure ?? new LogicException(\sprintf('None of the KMS clients that wrapped the ciphertext ("%s") is registered on the composite client.', implode('", "', $unregistered)));
        } finally {
            $this->leaveOperation($context);
        }
    }

    /**
     * Prevents reentry within one execution context while allowing independent fibers.
     * An opaque decorator that calls back from a new fiber cannot be identified here.
     */
    private function enterOperation(): ?\Fiber
    {
        $fiber = \Fiber::getCurrent();
        if (null === $fiber) {
            if ($this->runningMain) {
                throw new CompositeKmsReentryException();
            }

            $this->runningMain = true;
        } else {
            if (isset($this->runningFibers[$fiber])) {
                throw new CompositeKmsReentryException();
            }

            $this->runningFibers[$fiber] = true;
        }

        return $fiber;
    }

    private function leaveOperation(?\Fiber $fiber): void
    {
        if (null === $fiber) {
            $this->runningMain = false;
        } else {
            unset($this->runningFibers[$fiber]);
        }
    }

    /**
     * Resolves a member, checking that it is a full KMS client.
     *
     * One that could wrap but not read back would be a wrapping nothing reads, which is not the
     * redundancy the configuration claims. The check runs on every resolution, since the container
     * hands the members out lazily.
     */
    private function member(string $name): DataKeyGeneratorInterface&DecrypterInterface&EncrypterInterface
    {
        if (!$this->clients->has($name)) {
            throw new LogicException(\sprintf('No KMS client named "%s" is registered on the composite client.', $name));
        }

        $member = $this->clients->get($name);
        $backend = $member;
        while ($backend instanceof TraceableKms) {
            $backend = $backend->getKms();
        }

        if ($backend instanceof self) {
            throw new LogicException('A composite KMS client cannot be a member of another composite client.');
        }

        if (!$member instanceof DataKeyGeneratorInterface || !$member instanceof DecrypterInterface || !$member instanceof EncrypterInterface) {
            throw new LogicException(\sprintf('The KMS client "%s" cannot be a member of a composite client: a member encrypts, decrypts and generates data keys, so that it reads back everything it wraps.', $name));
        }

        return $member;
    }

    /**
     * @param array<string, Ciphertext> $wrappings
     */
    private static function frame(array $wrappings): string
    {
        $blob = \chr(self::VERSION).\chr(\count($wrappings));
        foreach ($wrappings as $name => $wrapping) {
            if (0xFFFF < \strlen($wrapping->keyId)) {
                throw new InvalidArgumentException(\sprintf('The master key id of the KMS client "%s" is too long to be recorded in a ciphertext: max %d bytes, %d given.', $name, 0xFFFF, \strlen($wrapping->keyId)));
            }

            $blob .= \chr(\strlen($name)).$name
                .pack('n', \strlen($wrapping->keyId)).$wrapping->keyId
                .pack('N', \strlen($wrapping->blob)).$wrapping->blob;
        }

        return $blob;
    }

    /**
     * Returns the wrappings in frame order, the minter's first, or null when the blob is not a frame.
     *
     * Such a blob is a ciphertext a member wrote on its own, or one nothing can read, which the
     * members will say.
     *
     * @return array<string, Ciphertext>|null
     */
    private static function parse(Ciphertext $ciphertext): ?array
    {
        $blob = $ciphertext->blob;
        $length = \strlen($blob);

        if ($length < 2 || self::VERSION !== \ord($blob[0])) {
            return null;
        }

        $count = \ord($blob[1]);
        $offset = 2;
        $wrappings = [];
        for ($i = 0; $i < $count; ++$i) {
            $name = self::read($blob, $offset, $length, 1);
            $keyId = self::read($blob, $offset, $length, 2);
            $wrapped = self::read($blob, $offset, $length, 4);

            if (null === $name || null === $keyId || null === $wrapped || '' === $name) {
                return null;
            }

            $wrappings[$name] = new Ciphertext($wrapped, $keyId);
        }

        return $offset === $length && $wrappings ? $wrappings : null;
    }

    /**
     * Reads a field prefixed with its big-endian length on `$lengthBytes` bytes and moves past it.
     *
     * Returns null where the blob ends first.
     */
    private static function read(string $blob, int &$offset, int $length, int $lengthBytes): ?string
    {
        if ($offset + $lengthBytes > $length) {
            return null;
        }

        $fieldLength = (int) unpack(match ($lengthBytes) {
            1 => 'C',
            2 => 'n',
            4 => 'N',
            default => throw new LogicException(\sprintf('A length is prefixed on 1, 2 or 4 bytes, not %d.', $lengthBytes)),
        }, $blob, $offset)[1];
        $offset += $lengthBytes;

        if ($offset + $fieldLength > $length) {
            return null;
        }

        $field = substr($blob, $offset, $fieldLength);
        $offset += $fieldLength;

        return $field;
    }
}
