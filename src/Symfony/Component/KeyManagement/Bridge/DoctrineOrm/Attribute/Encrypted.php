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
     * @param string $key The master key encrypting the values, or the scope of their data key when the encrypter uses a data key store
     */
    public function __construct(
        public string $key,
    ) {
    }
}
