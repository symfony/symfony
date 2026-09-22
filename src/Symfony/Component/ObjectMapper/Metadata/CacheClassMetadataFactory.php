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
 * Caches the class metadata of a source and target pair in a PSR-6 pool.
 *
 * @phpstan-import-type ClassMetadata from ClassMetadataFactoryInterface
 *
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class CacheClassMetadataFactory implements ClassMetadataFactoryInterface
{
    use CacheKeyTrait;

    /**
     * @var array<string, ClassMetadata>
     */
    private array $loaded = [];

    public function __construct(
        private readonly ClassMetadataFactoryInterface $decorated,
        private readonly CacheItemPoolInterface $cacheItemPool,
    ) {
    }

    public function create(object $source, object $target): array
    {
        $key = 'class_metadata__'.$this->encodeClass($source::class).'__'.$this->encodeClass($target::class);

        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }

        $item = $this->cacheItemPool->getItem($key);
        if ($item->isHit()) {
            return $this->loaded[$key] = $item->get();
        }

        $metadata = $this->decorated->create($source, $target);
        $this->cacheItemPool->save($item->set($metadata));

        return $this->loaded[$key] = $metadata;
    }
}
