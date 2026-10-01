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
 * What of the value is indexed is a {@see BlindIndex\ProjectionInterface}. Where the index key
 * comes from is the implementation: {@see BlindIndex} unwraps a wrapped key through the KMS, and
 * {@see StoredKeyBlindIndex} names one a store holds.
 *
 * The key is a data key of its own, which nothing else uses and which **never rotates**: every
 * index already written was derived under it, and a new key matches none of them. Rotating it
 * means reindexing, which means reading every row.
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
interface BlindIndexInterface
{
    /**
     * @return string 64 lowercase hexadecimal characters, whatever the algorithm and the length of the value
     */
    public function of(#[\SensitiveParameter] string $value): string;
}
