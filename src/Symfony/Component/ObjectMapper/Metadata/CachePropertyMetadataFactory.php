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
 * Caches the property metadata of a source and target pair in a PSR-6 pool, as a single item
 * covering every property declared by either class.
 *
 * @phpstan-import-type PropertyMetadata from PropertyMetadataFactoryInterface
 *
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class CachePropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    use CacheKeyTrait;

    /**
     * @var array<class-string, array<class-string, array<string, PropertyMetadata>>>
     */
    private array $loaded = [];

    public function __construct(
        private readonly PropertyMetadataFactoryInterface $decorated,
        private readonly CacheItemPoolInterface $cacheItemPool,
        private readonly PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
    ) {
    }

    public function create(object $source, object $target, string $property): array
    {
        if (!isset($this->loaded[$source::class][$target::class])) {
            $key = 'property_metadata__'.$this->encodeClass($source::class).'__'.$this->encodeClass($target::class);
            $item = $this->cacheItemPool->getItem($key);

            if ($item->isHit()) {
                $this->loaded[$source::class][$target::class] = $item->get();
            } else {
                $metadata = [];
                foreach ([...$this->propertyNameCollectionFactory->create($source), ...$this->propertyNameCollectionFactory->create($target)] as $name) {
                    $metadata[$name] ??= $this->decorated->create($source, $target, $name);
                }

                $this->cacheItemPool->save($item->set($metadata));
                $this->loaded[$source::class][$target::class] = $metadata;
            }
        }

        return $this->loaded[$source::class][$target::class][$property] ??= $this->decorated->create($source, $target, $property);
    }
}
