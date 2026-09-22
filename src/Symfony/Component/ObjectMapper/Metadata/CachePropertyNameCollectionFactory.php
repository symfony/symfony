<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Metadata;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Caches the property names of a class in a PSR-6 pool.
 *
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class CachePropertyNameCollectionFactory implements PropertyNameCollectionFactoryInterface
{
    use CacheKeyTrait;

    /**
     * @var array<string, list<string>>
     */
    private array $loaded = [];

    public function __construct(
        private readonly PropertyNameCollectionFactoryInterface $decorated,
        private readonly CacheItemPoolInterface $cacheItemPool,
    ) {
    }

    public function create(object $object): array
    {
        $key = 'property_names__'.$this->encodeClass($object::class);

        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }

        $item = $this->cacheItemPool->getItem($key);
        if ($item->isHit()) {
            return $this->loaded[$key] = $item->get();
        }

        $names = $this->decorated->create($object);
        $this->cacheItemPool->save($item->set($names));

        return $this->loaded[$key] = $names;
    }
}
