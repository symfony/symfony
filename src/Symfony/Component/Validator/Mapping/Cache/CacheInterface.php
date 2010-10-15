<?php

namespace Symfony\Component\Validator\Mapping\Cache;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
    function has($class);

    /**
     * Returns the metadata for the given class from the cache
     *
     * @param string $class Class Name
     *
     * @return ClassMetadata
     */
    function read($class);

    /**
     * Stores a class metadata in the cache
     *
     * @param ClassMetadata $metadata A Class Metadata
     */
    function write(ClassMetadata $metadata);
}