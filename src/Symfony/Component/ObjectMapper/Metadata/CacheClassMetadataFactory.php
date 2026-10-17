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
     * @var array<class-string, array<class-string, ClassMetadata>>
     */
    private array $loaded = [];

    public function __construct(
        private readonly ClassMetadataFactoryInterface $decorated,
        private readonly CacheItemPoolInterface $cacheItemPool,
    ) {
    }

    public function create(object $source, object $target): array
    {
        return $this->loaded[$source::class][$target::class] ??= $this->load($source, $target);
    }

    /**
     * @return ClassMetadata
     */
    private function load(object $source, object $target): array
    {
        $item = $this->cacheItemPool->getItem('class_metadata__'.$this->encodeClass($source::class).'__'.$this->encodeClass($target::class));
        if ($item->isHit()) {
            return $item->get();
        }

        $metadata = $this->decorated->create($source, $target);
        $this->cacheItemPool->save($item->set($metadata));

        return $metadata;
    }
}
