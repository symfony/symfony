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

use Symfony\Bundle\FrameworkBundle\Exception\SecretNotFoundException;

/**
 * SecretStorageInterface defines an interface to retrieve secrets.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
interface SecretStorageInterface
{
    /**
     * Retrieves a decrypted secret from the storage.
     *
     * @throws SecretNotFoundException
     */
    public function getSecret(string $name): string;

    /**
     * Returns a list of all secrets indexed by their name.
     *
     * @param bool $reveal when true, returns the decrypted secret, null otherwise
     *
     * @return iterable a list of key => value pairs
     */
    public function listSecrets(bool $reveal = false): iterable;
}
