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
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @final
 */
class ChainSecretStorage implements SecretStorageInterface
{
    private $secretStorages;

    /**
     * @param SecretStorageInterface[] $secretStorages
     */
    public function __construct(iterable $secretStorages = [])
    {
        $this->secretStorages = $secretStorages;
    }

    /**
     * {@inheritdoc}
     */
    public function getSecret(string $name): string
    {
        foreach ($this->secretStorages as $secretStorage) {
            try {
                return $secretStorage->getSecret($name);
            } catch (SecretNotFoundException $e) {
                // ignore exception, to try the next storage
            }
        }

        throw new SecretNotFoundException($name);
    }

    /**
     * {@inheritdoc}
     */
    public function listSecrets(bool $reveal = false): iterable
    {
        foreach ($this->secretStorages as $secretStorage) {
            yield from $secretStorage->listSecrets($reveal);
        }
    }
}
