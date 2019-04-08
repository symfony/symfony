<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Secret\Storage;

/**
 * MutableSecretStorageInterface defines an interface to add and update a secrets in a storage.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface MutableSecretStorageInterface extends SecretStorageInterface
{
    /**
     * Adds or replaces a secret in the store.
     */
    public function setSecret(string $name, string $secret): void;

    /**
     * Removes a secret from the store.
     */
    public function removeSecret(string $name): void;
}
