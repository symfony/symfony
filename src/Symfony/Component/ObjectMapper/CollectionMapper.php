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

use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\WrappedMappingException;

/**
 * Map a collection of objects using the ObjectMapper
 *
 * @experimental
 *
 * @author Martin Komischke <martin.komischke@gmail.com>
 */
final class CollectionMapper implements CollectionMapperInterface
{
    public function __construct(
        private ObjectMapperInterface $mapper,
        private string $throwPolicy = CollectionMapperThrowPolicy::FAIL_SAFE
    ) {
    }

    public function map(iterable $sourceCollection, ?string $target = null): \Generator
    {
        return match ($this->throwPolicy) {
            CollectionMapperThrowPolicy::FAIL_EARLY => yield from $this->mapFailEarly($sourceCollection, $target),
            CollectionMapperThrowPolicy::FAIL_SAFE => yield from $this->mapFailSafe($sourceCollection, $target),
            CollectionMapperThrowPolicy::IGNORE_MAPPING_ERRORS => yield from $this->mapIgnoreMappingErrors($sourceCollection, $target)
        };
    }

    private function mapFailEarly(iterable $sourceCollection, ?string $target = null): \Generator
    {
        foreach ($sourceCollection as $sourceObject) {
            yield $this->mapper->map($sourceObject, $target);
        }
    }

    private function mapFailSafe(iterable $sourceCollection, ?string $target = null): \Generator
    {
        $exceptions = [];

        foreach ($sourceCollection as $sourceObject) {
            try {
                yield $this->mapper->map($sourceObject, $target);
            } catch (MappingException $ex) {
                $exceptions[] = $ex;
            }
        }

        if ($exceptions) {
            throw new WrappedMappingException($exceptions);
        }
    }

    private function mapIgnoreMappingErrors(iterable $sourceCollection, ?string $target = null): \Generator
    {
        foreach ($sourceCollection as $sourceObject) {
            try {
                yield $this->mapper->map($sourceObject, $target);
            } catch (MappingException) {
                /* Ignore */
            }
        }
    }
}
