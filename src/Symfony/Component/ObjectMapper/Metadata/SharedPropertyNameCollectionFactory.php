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
 * Keeps the property names returned by the decorated factory in memory for all the instances created with the same key.
 *
 * @internal
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class SharedPropertyNameCollectionFactory implements PropertyNameCollectionFactoryInterface
{
    /**
     * @var array<string, array<class-string, list<string>>>
     */
    private static array $loaded = [];

    public function __construct(
        private readonly PropertyNameCollectionFactoryInterface $decorated,
        private readonly string $key,
    ) {
    }

    public function create(object $object): array
    {
        return self::$loaded[$this->key][$object::class] ??= $this->decorated->create($object);
    }
}
