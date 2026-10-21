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

/**
 * Keeps the property metadata returned by the decorated factory in memory for all the instances created with the same key.
 *
 * @phpstan-import-type PropertyMetadata from PropertyMetadataFactoryInterface
 *
 * @internal
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class SharedPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    /**
     * @var array<string, array<class-string, array<class-string, array<string, PropertyMetadata>>>>
     */
    private static array $loaded = [];

    public function __construct(
        private readonly PropertyMetadataFactoryInterface $decorated,
        private readonly string $key,
    ) {
    }

    public function create(object $source, object $target, string $property): array
    {
        return self::$loaded[$this->key][$source::class][$target::class][$property] ??= $this->decorated->create($source, $target, $property);
    }
}
