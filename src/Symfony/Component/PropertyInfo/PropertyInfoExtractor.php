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
     * @param iterable|PropertyListExtractorInterface[]          $listExtractors
     * @param iterable|PropertyTypeExtractorInterface[]          $typeExtractors
     * @param iterable|PropertyDescriptionExtractorInterface[]   $descriptionExtractors
     * @param iterable|PropertyAccessExtractorInterface[]        $accessExtractors
     * @param iterable|PropertyInitializableExtractorInterface[] $initializableExtractors
     */
    public function __construct(iterable $listExtractors = [], iterable $typeExtractors = [], iterable $descriptionExtractors = [], iterable $accessExtractors = [], iterable $initializableExtractors = [])
    {
        $this->listExtractors = $listExtractors;
        $this->typeExtractors = $typeExtractors;
        $this->descriptionExtractors = $descriptionExtractors;
        $this->accessExtractors = $accessExtractors;
        $this->initializableExtractors = $initializableExtractors;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = []): ?array
    {
        return $this->extract($this->listExtractors, 'getProperties', [$class, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = []): ?string
    {
        return $this->extract($this->descriptionExtractors, 'getShortDescription', [$class, $property, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function getLongDescription($class, $property, array $context = []): ?string
    {
        return $this->extract($this->descriptionExtractors, 'getLongDescription', [$class, $property, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = []): ?array
    {
        return $this->extract($this->typeExtractors, 'getTypes', [$class, $property, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = []): ?bool
    {
        return $this->extract($this->accessExtractors, 'isReadable', [$class, $property, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = []): ?bool
    {
        return $this->extract($this->accessExtractors, 'isWritable', [$class, $property, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function isInitializable(string $class, string $property, array $context = []): ?bool
    {
        return $this->extract($this->initializableExtractors, 'isInitializable', [$class, $property, $context]);
    }

    /**
     * Iterates over registered extractors and return the first value found.
     *
     * @return mixed
     */
    private function extract(iterable $extractors, string $method, array $arguments)
    {
        foreach ($extractors as $extractor) {
            if (null !== $value = $extractor->{$method}(...$arguments)) {
                return $value;
            }
        }

        return null;
    }
}
