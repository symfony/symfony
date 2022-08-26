<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

/**
 * Default {@see PropertyInfoExtractorInterface} implementation.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class PropertyInfoExtractor implements PropertyInfoExtractorInterface, PropertyInitializableExtractorInterface
{
    private $listExtractors;
    private $typeExtractors;
    private $descriptionExtractors;
    private $accessExtractors;
    private $initializableExtractors;

    /**
     * @param iterable<mixed, PropertyListExtractorInterface>          $listExtractors
     * @param iterable<mixed, PropertyTypeExtractorInterface>          $typeExtractors
     * @param iterable<mixed, PropertyDescriptionExtractorInterface>   $descriptionExtractors
     * @param iterable<mixed, PropertyAccessExtractorInterface>        $accessExtractors
     * @param iterable<mixed, PropertyInitializableExtractorInterface> $initializableExtractors
     */
    public function __construct(iterable $listExtractors = [], iterable $typeExtractors = [], iterable $descriptionExtractors = [], iterable $accessExtractors = [], iterable $initializableExtractors = [])
    {
        $this->listExtractors = $listExtractors;
        $this->typeExtractors = $typeExtractors;
        $this->descriptionExtractors = $descriptionExtractors;
        $this->accessExtractors = $accessExtractors;
        $this->initializableExtractors = $initializableExtractors;
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        return $this->extract($this->listExtractors, 'getProperties', [$class, $context]);
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        return $this->extract($this->descriptionExtractors, 'getShortDescription', [$class, $property, $context]);
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        return $this->extract($this->descriptionExtractors, 'getLongDescription', [$class, $property, $context]);
    }

    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        return $this->extract($this->typeExtractors, 'getTypes', [$class, $property, $context]);
    }

    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        return $this->extract($this->accessExtractors, 'isReadable', [$class, $property, $context]);
    }

    public function isWritable(string $class, string $property, array $context = []): ?bool
    {
        return $this->extract($this->accessExtractors, 'isWritable', [$class, $property, $context]);
    }

    public function isInitializable(string $class, string $property, array $context = []): ?bool
    {
        return $this->extract($this->initializableExtractors, 'isInitializable', [$class, $property, $context]);
    }

    /**
     * Iterates over registered extractors and return the first value found.
     *
     * @param iterable<mixed, object> $extractors
     * @param list<mixed>             $arguments
     */
    private function extract(iterable $extractors, string $method, array $arguments): mixed
    {
        foreach ($extractors as $extractor) {
            if (null !== $value = $extractor->{$method}(...$arguments)) {
                return $value;
            }
        }

        return null;
    }
}
