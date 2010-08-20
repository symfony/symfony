<?php

namespace Symfony\Component\Validator\Mapping\Cache;

use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Persists ClassMetadata instances in a cache
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface CacheInterface
{
    /**
     * Returns whether metadata for the given class exists in the cache
     *
     * @param string $class
     */
    public function has($class);

    /**
     * Returns the metadata for the given class from the cache
     *
     * @param string $class
     * @return ClassMetadata
     */
    public function read($class);

    /**
     * Stores a class metadata in the cache
     *
     * @param $class
     * @param $metadata
     */
    public function write(ClassMetadata $metadata);
}