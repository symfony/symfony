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
 * loss of one for good is recovered by adding another member and rewrapping what the store holds.
 *
 * The first member is the one the key id given to each call names a master key on, and the one it
 * generates data keys with; the others wrap under a master key of their own, fixed once in the
 * configuration. Each member is a full path to the plaintext, so the list is worth keeping to
 * providers one would otherwise lose sleep over losing, and each of them deserves the protection
 * the first one gets.
 *
 * Writing goes through every member, and a member that cannot wrap fails the whole call: a
 * ciphertext missing one wrapping is a ciphertext less redundant than the configuration claims,
 * and the point of this client is that the claim holds. Reading only needs one, and the failure
 * reported when none answers is the first member's, whose failure says the most.
 *
 * The blob is laid out as `[0x01][count]` followed, for each wrapping, by the member's name, the
 * master key it used and its own blob, each prefixed with its length on one, two and four bytes.
 * The name is what lets a wrapping find its member back; a member dropped from the configuration
 * leaves wrappings that are passed over, and one added later is found in the ciphertexts written
 * from then on.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class RedundantKms implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface
{
    private const int VERSION = 1;

    /**
     * @param ContainerInterface    $clients    KMS clients, indexed by name
     * @param string                $client     Name of the member the key id given to each call names a master key on; it generates the data keys and is asked first to read
     * @param array<string, string> $recipients Master key, indexed by member name, that every ciphertext is also wrapped under
     */
    public function __construct(
        private readonly ContainerInterface $clients,
        private readonly string $client,
        private readonly array $recipients,
    ) {
        if (isset($recipients[$client])) {
            throw new InvalidArgumentException(\sprintf('The KMS client "%s" is the one every call names a master key on, so it cannot be listed among the recipients as well.', $client));
        }

        foreach ([$client, ...array_keys($recipients)] as $name) {
            if ('' === $name || 0xFF < \strlen($name)) {
                throw new InvalidArgumentException(\sprintf('A KMS client name is between 1 and 255 bytes long to be recorded in a ciphertext, "%s" is %d bytes long.', $name, \strlen($name)));
            }
        }
    }

    /**
     * Each member is required to decrypt as well as to encrypt, at the time it is asked to encrypt:
     * a member that could only wrap would be a wrapping nothing reads back, which is not the
     * redundancy the configuration claims.
     */
    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        $wrappings = [$this->client => $this->member($this->client, EncrypterInterface::class, DecrypterInterface::class)->encrypt($keyId, $plaintext, $aad, $deterministic)];
        foreach ($this->recipients as $name => $recipientKeyId) {
            $wrappings[$name] = $this->member($name, EncrypterInterface::class, DecrypterInterface::class)->encrypt($recipientKeyId, $plaintext, $aad, $deterministic);
        }

        return new Ciphertext(self::frame($wrappings), $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        return $this->readThroughAny(self::parse($ciphertext), static fn (DecrypterInterface $member, Ciphertext $wrapped): string => $member->decrypt($wrapped, $aad), DecrypterInterface::class);
    }

    /**
     * The data key is minted by the first member and wrapped by the others while its plaintext is
     * still around, inside the closure {@see DataKey::use()} hands it to; each of them is required
     * to unwrap as well, for the reason {@see encrypt()} gives. The DataKey returned takes a buffer
     * of its own, for the reason {@see DataKeyHandle} gives: the minted DataKey wipes what it held
     * once the closure returns, and a shared buffer would leave one of the two sides unwiped.
     */
    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        $dataKey = $this->member($this->client, DataKeyGeneratorInterface::class)->generateDataKey($keyId, $length, $aad);

        return $dataKey->use(function (#[\SensitiveParameter] string $plaintext) use ($dataKey, $keyId, $aad): DataKey {
            $wrappings = [$this->client => $dataKey->wrapped];
            foreach ($this->recipients as $name => $recipientKeyId) {
                $wrappings[$name] = $this->member($name, EncrypterInterface::class, DataKeyGeneratorInterface::class)->encrypt($recipientKeyId, $plaintext, $aad);
            }

            if ('' !== $plaintext) {
                $plaintext[0] = $plaintext[0];
            }

            return new DataKey($plaintext, new Ciphertext(self::frame($wrappings), $keyId));
        });
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return $this->readThroughAny(self::parse($wrapped), static fn (DataKeyGeneratorInterface $member, Ciphertext $wrapping): DataKey => $member->unwrapDataKey($wrapping, $aad), DataKeyGeneratorInterface::class);
    }

    /**
     * Asks the first member, then the recipients in their configured order, then whoever else
     * left a wrapping in the ciphertext. A member that is not registered anymore cannot be asked
     * and is passed over; one that fails is passed over too, and its failure is the one reported
     * when none of them answers.
     *
     * @template T
     * @template M of DecrypterInterface|DataKeyGeneratorInterface
     *
     * @param array<string, Ciphertext>  $wrappings
     * @param \Closure(M, Ciphertext): T $read
     * @param class-string<M>            $type
     *
     * @return T
     */
    private function readThroughAny(array $wrappings, \Closure $read, string $type): mixed
    {
        $rank = array_flip([$this->client, ...array_keys($this->recipients)]);
        uksort($wrappings, static fn (string $a, string $b): int => ($rank[$a] ?? \PHP_INT_MAX) <=> ($rank[$b] ?? \PHP_INT_MAX));

        $failure = null;
        $unregistered = [];
        foreach ($wrappings as $name => $wrapping) {
            if (!$this->clients->has($name)) {
                $unregistered[] = $name;
                continue;
            }

            try {
                return $read($this->member($name, $type), $wrapping);
            } catch (\RuntimeException $e) {
                $failure ??= $e;
            }
        }

        throw $failure ?? new LogicException(\sprintf('None of the KMS clients that wrapped the ciphertext ("%s") is registered on the redundant client.', implode('", "', $unregistered)));
    }

    /**
     * @template M of object
     *
     * @param class-string<M> $type
     * @param class-string    ...$types
     *
     * @return M
     */
    private function member(string $name, string $type, string ...$types): object
    {
        if (!$this->clients->has($name)) {
            throw new LogicException(\sprintf('No KMS client named "%s" is registered on the redundant client.', $name));
        }

        $member = $this->clients->get($name);
        foreach ([$type, ...$types] as $required) {
            if (!$member instanceof $required) {
                throw new LogicException(\sprintf('The KMS client "%s" does not implement "%s", which the redundant client needs from each of its members.', $name, $required));
            }
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
     * A blob that does not parse is a ciphertext this client did not produce, and is reported the
     * way any other unreadable ciphertext is, with no word on why.
     *
     * @return array<string, Ciphertext>
     */
    private static function parse(Ciphertext $ciphertext): array
    {
        $blob = $ciphertext->blob;
        $length = \strlen($blob);

        if ($length < 2 || self::VERSION !== \ord($blob[0])) {
            throw new DecryptionFailedException();
        }

        $count = \ord($blob[1]);
        $offset = 2;
        $wrappings = [];
        for ($i = 0; $i < $count; ++$i) {
            $name = self::read($blob, $offset, $length, 1);
            $keyId = self::read($blob, $offset, $length, 2);
            $wrapped = self::read($blob, $offset, $length, 4);

            if ('' === $name) {
                throw new DecryptionFailedException();
            }

            $wrappings[$name] = new Ciphertext($wrapped, $keyId);
        }

        if ($offset !== $length || !$wrappings) {
            throw new DecryptionFailedException();
        }

        return $wrappings;
    }

    /**
     * Reads a field prefixed with its big-endian length on `$lengthBytes` bytes and moves past it.
     */
    private static function read(string $blob, int &$offset, int $length, int $lengthBytes): string
    {
        if ($offset + $lengthBytes > $length) {
            throw new DecryptionFailedException();
        }

        $fieldLength = match ($lengthBytes) {
            1 => \ord($blob[$offset]),
            2 => unpack('n', $blob, $offset)[1],
            4 => unpack('N', $blob, $offset)[1],
        };
        $offset += $lengthBytes;

        if ($offset + $fieldLength > $length) {
            throw new DecryptionFailedException();
        }

        $field = substr($blob, $offset, $fieldLength);
        $offset += $fieldLength;

        return $field;
    }
}
