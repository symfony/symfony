<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

use Symfony\Component\ObjectMapper\Exception\WrappedMappingException;

/**
 * Map a collection of objects using the ObjectMapper
 *
 * @experimental
 *
 * @author Martin Komischke <martin.komischke@gmail.com>
 */
interface CollectionMapperInterface
{
    /**
     * @template T of object
     * 
     * @param iterable<T> $sourceCollection The objects to map from
     * @return \Generator<int, object, mixed, void> yields a target object for each source object
     *
     * @throws WrappedMappingException      When mapping at least one of the source objects has failed.
     * @throws MappingException          When the mapping configuration is wrong
     */
    public function map(iterable $sourceCollection, array|string|null $target = null): \Generator;
}
