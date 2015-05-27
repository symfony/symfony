<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping\Factory;

use Doctrine\Common\Cache\Cache;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

/**
 * Returns a {@link ClassMetadata}.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadataFactory implements ClassMetadataFactoryInterface
{
    /**
     * @var LoaderInterface
     */
    private $loader;
    /**
     * @var Cache
     */
    private $cache;
    /**
     * @var string
     */
    private $className;
    /**
     * @var array
     */
    private $loadedClasses;

    /**
     * @param LoaderInterface $loader
     * @param Cache|null      $cache
     * @param string          $className
     */
    public function __construct(LoaderInterface $loader, Cache $cache = null, $className = 'Symfony\Component\Serializer\Mapping\ClassMetadata')
    {
        $this->loader = $loader;
        $this->cache = $cache;
        $this->className = $className;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFor($value)
    {
        $class = $this->getClass($value);
        if (!$class) {
            throw new InvalidArgumentException(sprintf('Cannot create metadata for non-objects. Got: "%s"', gettype($value)));
        }

        if (isset($this->loadedClasses[$class])) {
            return $this->loadedClasses[$class];
        }

        if ($this->cache && ($this->loadedClasses[$class] = $this->cache->fetch($class))) {
            return $this->loadedClasses[$class];
        }

        if (!class_exists($class) && !interface_exists($class)) {
            throw new InvalidArgumentException(sprintf('The class or interface "%s" does not exist.', $class));
        }

        $classMetadata = new $this->className($class);
        $this->loader->loadClassMetadata($classMetadata);

        $reflectionClass = $classMetadata->getReflectionClass();

        // Include metadata from the parent class
        if ($parent = $reflectionClass->getParentClass()) {
            $classMetadata->merge($this->getMetadataFor($parent->name));
        }

        // Include metadata from all implemented interfaces
        foreach ($reflectionClass->getInterfaces() as $interface) {
            $classMetadata->merge($this->getMetadataFor($interface->name));
        }

        if ($this->cache) {
            $this->cache->save($class, $classMetadata);
        }

        return $this->loadedClasses[$class] = $classMetadata;
    }

    /**
     * {@inheritdoc}
     */
    public function hasMetadataFor($value)
    {
        $class = $this->getClass($value);

        return class_exists($class) || interface_exists($class);
    }

    /**
     * Gets a class name for a given class or instance.
     *
     * @param mixed $value
     *
     * @return string|bool
     */
    private function getClass($value)
    {
        if (!is_object($value) && !is_string($value)) {
            return false;
        }

        return ltrim(is_object($value) ? get_class($value) : $value, '\\');
    }
}
