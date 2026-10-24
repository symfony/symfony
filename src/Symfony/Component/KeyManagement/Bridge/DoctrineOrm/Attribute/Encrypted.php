<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute;

/**
 * Encrypts the column of a property at rest.
 *
 *     #[ORM\Column(nullable: true)]
 *     #[Encrypted(key: 'speaker.contact')]
 *     public ?string $phone = null;
 *
 * The column keeps the type its mapping gives it, declared on the column or guessed from the type of the property.
 * The value is converted by that type first, then encrypted, and the column is stored as a binary one.
 *
 * The value is authenticated with the configured key and parent Doctrine type name.
 * Changing either requires rewriting existing values.
 * The parent PHP class can change while the type name remains the same.
 * The encrypted declaration's name and parent instance configuration aren't authenticated.
 * Columns with the same key and parent type name can read each other's values.
 * Give these columns different keys to keep them apart.
 *
 * Omitting the key requires a configured data key store and derives a scope from the physical schema, table and column.
 * Renaming a table or column then requires rewriting existing values.
 * An explicit key keeps the scope stable across these renames.
 *
 * {@see \Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\EncryptedColumnListener} gives the column the encrypted type registered for its key and its type.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Encrypted
{
    /**
     * @param string|null $key The master key encrypting the values, or the scope of their data key when the encrypter uses a data key store; null derives the scope from the physical column and requires a store
     */
    public function __construct(
        public ?string $key = null,
    ) {
    }
}
