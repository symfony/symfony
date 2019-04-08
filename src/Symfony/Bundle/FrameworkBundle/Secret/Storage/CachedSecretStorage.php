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

use Symfony\Contracts\Cache\CacheInterface;

/**
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class CachedSecretStorage implements SecretStorageInterface
{
    private $decoratedStorage;
    private $cache;

    public function __construct(SecretStorageInterface $decoratedStorage, CacheInterface $cache)
    {
        $this->decoratedStorage = $decoratedStorage;
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function getSecret(string $name): string
    {
        return $this->cache->get(md5(__CLASS__.$name), function () use ($name): string {
            return $this->decoratedStorage->getSecret($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function listSecrets(bool $reveal = false): iterable
    {
        return $this->decoratedStorage->listSecrets($reveal);
    }
}
