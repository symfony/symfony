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
 */
class PropertyInfoExtractor implements PropertyInfoExtractorInterface
{
    /**
     * @var \SplObjectStorage
     */
    private $extractors;

    /**
     * @param ExtractorInterface[] $extractors
     */
    public function __construct(array $extractors = array())
    {
        $this->extractors = new \SplObjectStorage();
        foreach ($extractors as $extractor) {
            $this->addExtractor($extractor);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addExtractor(ExtractorInterface $extractor)
    {
        $this->extractors->attach($extractor);
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = array())
    {
        return $this->extract(
            PropertyListExtractorInterface::class,
            'getProperties',
            array($class, $context)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = array())
    {
        return $this->extract(
            PropertyDescriptionExtractorInterface::class,
            'getShortDescription',
            array($class, $property, $context)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getLongDescription($class, $property, array $context = array())
    {
        return $this->extract(
            PropertyDescriptionExtractorInterface::class,
            'getLongDescription',
            array($class, $property, $context)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = array())
    {
        return $this->extract(
            PropertyTypeExtractorInterface::class,
            'getTypes',
            array($class, $property, $context)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = array())
    {
        return $this->extract(
            PropertyAccessExtractorInterface::class,
            'isReadable',
            array($class, $property, $context)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = array())
    {
        return $this->extract(
            PropertyAccessExtractorInterface::class,
            'isWritable',
            array($class, $property, $context)
        );
    }

    /**
     * Iterates over registered extractors and return the first value found.
     *
     * @param string $interfaceFilter
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    private function extract($interfaceFilter, $method, array $arguments)
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor instanceof $interfaceFilter) {
                $value = call_user_func_array(array($extractor, $method), $arguments);
                if (null !== $value) {
                    return $value;
                }
            }
        }
    }
}
