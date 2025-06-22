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
 * Map multiple objects using the ObjectMapperInterface
 *
 * @experimental
 *
 * @author Martin Komischke <martin.komischke@gmail.com>
 */
final class MapMultiple implements MapMultipleInterface
{
    public function __construct(private ObjectMapperInterface $mapper)
    {
    }

    public function yieldMappedObjects(array $sourceCollection, ?string $target = null): \Generator
    {
        $exceptions = [];

        foreach ($sourceCollection as $sourceObject) {
            try {
                yield $this->mapper->map($sourceObject, $target);
            } catch (\Throwable $ex) {
                $exceptions[] = $ex;
            }
        }

        if ($exceptions) {
            throw new MapMultipleAggregateException('Mapping source collection has failed.', $exceptions);
        }
    }
}
