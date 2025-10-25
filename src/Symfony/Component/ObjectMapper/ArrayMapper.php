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
 * Map an array of objects into the specified target array using the ObjectMapper.
 *
 * @internal
 *
 * @author Martin Komischke <martin.komischke@gmail.com>
 */
final class ArrayMapper
{
    public function __construct(
        private ObjectMapperInterface $mapper,
        private CollectionMapperThrowPolicy $throwPolicy = CollectionMapperThrowPolicy::FAIL_SAFE,
    ) {
    }

    public function map(array $source, array $target): void
    {
        if (array_keys($source) !== array_keys($target)) {
            throw new \InvalidArgumentException('Source and target array must have the same keys.');
        }

        match ($this->throwPolicy) {
            CollectionMapperThrowPolicy::FAIL_EARLY => $this->mapFailEarly($source, $target),
            CollectionMapperThrowPolicy::FAIL_SAFE => $this->mapFailSafe($source, $target),
            CollectionMapperThrowPolicy::IGNORE_MAPPING_ERRORS => $this->mapIgnoreMappingErrors($source, $target),
            default => throw new \Exception(\sprintf('Throw policy "%s" is not yet supported!', $this->throwPolicy)),
        };
    }

    private function mapFailEarly(array $source, array $target): void
    {
        for ($i = 0; $i < \count($source); ++$i) {
            $sourceObject = $source[$i];
            $targetObject = $target[$i];
            $this->mapper->map($sourceObject, $targetObject);
        }
    }

    private function mapFailSafe(array $source, array $target): void
    {
        $exceptions = [];

        for ($i = 0; $i < \count($source); ++$i) {
            $sourceObject = $source[$i];
            $targetObject = $target[$i];
            try {
                $this->mapper->map($sourceObject, $targetObject);
            } catch (MappingException $ex) {
                $exceptions[] = $ex;
            }
        }

        if ($exceptions) {
            throw new WrappedMappingException($exceptions);
        }
    }

    private function mapIgnoreMappingErrors(array $source, array $target): void
    {
        for ($i = 0; $i < \count($source); ++$i) {
            $sourceObject = $source[$i];
            $targetObject = $target[$i];

            try {
                $this->mapper->map($sourceObject, $targetObject);
            } catch (MappingException) {
                /* Ignore */
            }
        }
    }
}
