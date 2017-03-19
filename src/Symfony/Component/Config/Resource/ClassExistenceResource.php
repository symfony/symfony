<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * ClassExistenceResource represents a class existence.
 * Freshness is only evaluated against resource existence.
 *
 * The resource must be a fully-qualified class name.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassExistenceResource implements SelfCheckingResourceInterface, \Serializable
{
    private $resource;
    private $exists;

    /**
     * @param string $resource The fully-qualified class name
     */
    public function __construct($resource)
    {
        $this->resource = $resource;
        $this->exists = class_exists($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->resource;
    }

    /**
     * @return string The file path to the resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($timestamp)
    {
        return class_exists($this->resource) === $this->exists;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array($this->resource, $this->exists));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->resource, $this->exists) = unserialize($serialized);
    }
}
