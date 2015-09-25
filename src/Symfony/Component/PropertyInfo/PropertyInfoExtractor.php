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
     * @var PropertyListExtractorInterface[]
     */
    private $listExtractors;

    /**
     * @var PropertyTypeExtractorInterface[]
     */
    private $typeExtractors;

    /**
     * @var PropertyDescriptionExtractorInterface[]
     */
    private $descriptionExtractors;

    /**
     * @var PropertyAccessExtractorInterface[]
     */
    private $accessExtractors;

    /**
     * @param PropertyListExtractorInterface[]        $listExtractors
     * @param PropertyTypeExtractorInterface[]        $typeExtractors
     * @param PropertyDescriptionExtractorInterface[] $descriptionExtractors
     * @param PropertyAccessExtractorInterface[]      $accessExtractors
     */
    public function __construct(array $listExtractors = array(), array $typeExtractors = array(),  array $descriptionExtractors = array(), array $accessExtractors = array())
    {
        $this->listExtractors = $listExtractors;
        $this->typeExtractors = $typeExtractors;
        $this->descriptionExtractors = $descriptionExtractors;
        $this->accessExtractors = $accessExtractors;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = array())
    {
        return $this->extract($this->listExtractors, 'getProperties', array($class, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = array())
    {
        return $this->extract($this->descriptionExtractors, 'getShortDescription', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getLongDescription($class, $property, array $context = array())
    {
        return $this->extract($this->descriptionExtractors, 'getLongDescription', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = array())
    {
        return $this->extract($this->typeExtractors, 'getTypes', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = array())
    {
        return $this->extract($this->accessExtractors, 'isReadable', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = array())
    {
        return $this->extract($this->accessExtractors, 'isWritable', array($class, $property, $context));
    }

    /**
     * Iterates over registered extractors and return the first value found.
     *
     * @param array  $extractors
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    private function extract(array $extractors, $method, array $arguments)
    {
        foreach ($extractors as $extractor) {
            $value = call_user_func_array(array($extractor, $method), $arguments);
            if (null !== $value) {
                return $value;
            }
        }
    }
}
