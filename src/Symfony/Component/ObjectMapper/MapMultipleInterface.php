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

use Symfony\Component\ObjectMapper\Exception\MapMultipleAggregateException;

/**
 * Map multiple objects using the ObjectMapper
 *
 * @experimental
 *
 * @author Martin Komischke <martin.komischke@gmail.com>
 */
interface MapMultipleInterface
{
    /**
     * @template T of object
     * 
     * @param array<T> $sourceCollection The array of objects to map from
     * @return \Generator<int, object, mixed, void> yields a target object for each source object
     *
     * @throws MapMultipleAggregateException      When mapping at least one of the source objects has failed.
     */
    public function yieldMappedObjects(array $sourceCollection, string|null $target = null): \Generator;
}
