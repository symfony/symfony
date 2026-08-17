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

use Symfony\Component\KeyManagement\BlindIndex\AlgorithmInterface;
use Symfony\Component\KeyManagement\BlindIndex\HmacSha256;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;

/**
 * A searchable trace of a value whose encrypted column cannot be searched.
 *
 * Encryption is randomized, by design and without a way around it, so two encryptions of the same
 * value differ and `WHERE email = ?` never matches. The usual answer is to keep a blind index in a
 * sibling column: a keyed digest of the value, equal for equal values, and matched exactly.
 *
 *     $user->setEmail($email);
 *     $user->setEmailIndex($index->of($email));                      // on the way in
 *
 *     $repository->findOneBy(['emailIndex' => $index->of($email)]);  // and on the way out
 *
 * The first of those two lines is the one that gets forgotten, and a row whose tag was not written
 * is a row no search returns. On a Doctrine entity, the `BlindIndexed` attribute of
 * `symfony/doctrine-orm-key-management` says on the index column where its value comes from and has
 * a listener fill it on every flush.
 *
 * This class indexes the value it is given, byte for byte. Anything the value has to go through
 * first belongs to a subclass overriding {@see project()}, and that is where it has to live rather
 * than at the call sites: an index computed on a trimmed value and searched on an untrimmed one
 * silently matches nothing, which is the failure this whole arrangement exists to avoid. The
 * component ships {@see BlindIndex\Email} and {@see BlindIndex\EmailDomain}; anything more specific
 * to a domain, a national identifier or an account number, is a handful of lines in an application.
 *
 * The key is a data key of its own, wrapped by the KMS and unwrapped once per process, so the
 * plaintext never leaves memory and the KMS is not reached again whatever the number of values
 * indexed. Mint it once and keep the wrapped form in the configuration:
 *
 *     bin/console key-management:generate-data-key alias/app-key
 *
 * It must be a key nothing else uses, and above all one that **never rotates**: every index already
 * written was derived under it, and a new key matches none of them. Rotating it means reindexing,
 * which means reading every row.
 *
 * **What this leaks.** Equal values give equal tags, so anyone reading the column learns which rows
 * share a value, and how often each occurs. On a column with few distinct values, or one whose
 * distribution is known, that is enough to recover the values themselves by frequency analysis: a
 * country, a status, a birth year. Index what is high-entropy and looked up by equality, an address
 * or an account number, and leave the rest to a decrypted scan.
 *
 * Prefix, substring and range searches are this same construction over several tags per row, held
 * in a column the database can intersect, `text[]` with a GIN index on PostgreSQL. What those tags
 * are, and how much more they leak, is a decision that belongs to the application.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
class BlindIndex
{
    private ?DataKeyHandle $key = null;

    /**
     * @param Ciphertext $wrappedKey wrapped data key the tags are derived under, dedicated to this
     *                               index and never rotated
     */
    public function __construct(
        private readonly DataKeyGeneratorInterface $kms,
        private readonly Ciphertext $wrappedKey,
        private readonly AlgorithmInterface $algorithm = new HmacSha256(),
    ) {
    }

    /**
     * @return string 64 lowercase hexadecimal characters, whatever the algorithm and the length of
     *                the value
     *
     * @throws DecryptionFailedException If the index key cannot be unwrapped
     */
    final public function of(#[\SensitiveParameter] string $value): string
    {
        $value = $this->project($value);
        $this->key ??= new DataKeyHandle('blind-index', $this->kms->unwrapDataKey($this->wrappedKey));

        return bin2hex($this->key->use(fn (#[\SensitiveParameter] string $key): string => $this->algorithm->tag($value, $key)));
    }

    /**
     * What of the value is actually indexed, applied on the way in and on the way out alike.
     */
    protected function project(#[\SensitiveParameter] string $value): string
    {
        return $value;
    }
}
