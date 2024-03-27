<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Cache;

use Twig\Cache\CacheInterface;

/**
 * Chains several caches together.
 *
 * Cached items are fetched from the first cache having them in its data store.
 * They are saved and deleted in all adapters at once.
 */
class ChainCache implements CacheInterface
{
    /**
     * @param CacheInterface[] $caches The ordered list of caches used to store and fetch cached items
     */
    public function __construct(private array $caches)
    {
        if ([] === $caches) {
            throw new \InvalidArgumentException('At least one cache must be specified.');
        }

        foreach ($caches as $cache) {
            if (!$cache instanceof CacheInterface) {
                throw new \InvalidArgumentException(sprintf('The class "%s" does not implement the "%s" interface.', get_debug_type($cache), CacheInterface::class));
            }
        }
    }

    public function generateKey(string $name, string $className): string
    {
        return $name.'#'.$className;
    }

    public function write(string $key, string $content): void
    {
        [$name, $className] = explode('#', $key, 2);

        foreach ($this->caches as $cache) {
            $cache->write($cache->generateKey($name, $className), $content);
        }
    }

    public function load(string $key): void
    {
        [$name, $className] = explode('#', $key, 2);

        foreach ($this->caches as $cache) {
            $cache->load($cache->generateKey($name, $className));

            if (class_exists($className, false)) {
                break;
            }
        }
    }

    public function getTimestamp(string $key): int
    {
        [$name, $className] = explode('#', $key, 2);

        foreach ($this->caches as $cache) {
            $timestamp = $cache->getTimestamp($cache->generateKey($name, $className));
            if ($timestamp > 0) {
                return $timestamp;
            }
        }

        return 0;
    }
}
