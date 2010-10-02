<?php

namespace Symfony\Component\Validator\Mapping;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\Cache\CacheInterface;

/**
 * Implementation of ClassMetadataFactoryInterface
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class ClassMetadataFactory implements ClassMetadataFactoryInterface
{
    /**
     * The loader for loading the class metadata
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * The cache for caching class metadata
     * @var CacheInterface
     */
    protected $cache;

    protected $loadedClasses = array();

    public function __construct(LoaderInterface $loader, CacheInterface $cache = null)
    {
        $this->loader = $loader;
        $this->cache = $cache;
    }

    public function getClassMetadata($class)
    {
        $class = ltrim($class, '\\');

        if (!isset($this->loadedClasses[$class])) {
            if ($this->cache !== null && $this->cache->has($class)) {
                $this->loadedClasses[$class] = $this->cache->read($class);
            } else {
                $metadata = new ClassMetadata($class);

                // Include constraints from the parent class
                if ($parent = $metadata->getReflectionClass()->getParentClass()) {
                    $metadata->mergeConstraints($this->getClassMetadata($parent->getName()));
                }

                // Include constraints from all implemented interfaces
                foreach ($metadata->getReflectionClass()->getInterfaces() as $interface) {
                    $metadata->mergeConstraints($this->getClassMetadata($interface->getName()));
                }

                $this->loader->loadClassMetadata($metadata);

                $this->loadedClasses[$class] = $metadata;

                if ($this->cache !== null) {
                    $this->cache->write($metadata);
                }
            }
        }

        return $this->loadedClasses[$class];
    }
}