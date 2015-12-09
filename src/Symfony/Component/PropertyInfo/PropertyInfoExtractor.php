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

use Doctrine\Common\Cache\Cache;

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
     * @var Cache
     */
    private $cache;

    /**
     * @var array
     */
    private $arrayCache = array();

    /**
     * @param PropertyListExtractorInterface[]        $listExtractors
     * @param PropertyTypeExtractorInterface[]        $typeExtractors
     * @param PropertyDescriptionExtractorInterface[] $descriptionExtractors
     * @param PropertyAccessExtractorInterface[]      $accessExtractors
     * @param Cache|null                              $cache
     */
    public function __construct(array $listExtractors = array(), array $typeExtractors = array(),  array $descriptionExtractors = array(), array $accessExtractors = array(), Cache $cache = null)
    {
        $this->listExtractors = $listExtractors;
        $this->typeExtractors = $typeExtractors;
        $this->descriptionExtractors = $descriptionExtractors;
        $this->accessExtractors = $accessExtractors;
        $this->cache = $cache;
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
        $key = $method.serialize($arguments);

        if (isset($this->arrayCache[$key])) {
            return $this->arrayCache[$key];
        }

        if ($this->cache && $value = $this->cache->fetch($key)) {
            return $this->arrayCache[$key] = $value;
        }

        foreach ($extractors as $extractor) {
            $value = call_user_func_array(array($extractor, $method), $arguments);
            if (null !== $value) {
                break;
            }
        }

        if ($this->cache) {
            $this->cache->save($key, $value);
        }

        return $this->arrayCache[$key] = $value;
    }
}
