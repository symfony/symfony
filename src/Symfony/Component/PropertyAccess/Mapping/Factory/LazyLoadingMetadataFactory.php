<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Mapping\Factory;

use Symfony\Component\PropertyAccess\Exception\NoSuchMetadataException;
use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;
use Symfony\Component\PropertyAccess\Mapping\Loader\LoaderInterface;

/**
 * Creates new {@link ClassMetadataInterface} instances.
 *
 * Whenever {@link getMetadataFor()} is called for the first time with a given
 * class name or object of that class, a new metadata instance is created and
 * returned. On subsequent requests for the same class, the same metadata
 * instance will be returned.
 *
 * You can optionally pass a {@link LoaderInterface} instance to the constructor.
 * Whenever a new metadata instance is created, it is passed to the loader,
 * which can configure the metadata based on configuration loaded from the
 * filesystem or a database. If you want to use multiple loaders, wrap them in a
 * {@link LoaderChain}.
 *
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
class LazyLoadingMetadataFactory implements MetadataFactoryInterface
{
    /**
     * The loader for loading the class metadata.
     *
     * @var LoaderInterface|null
     */
    private $loader;

    /**
     * The loaded metadata, indexed by class name.
     *
     * @var ClassMetadata[]
     */
    protected $loadedClasses = array();

    /**
     * Creates a new metadata factory.
     *
     * @param LoaderInterface|null $loader The loader for configuring new metadata
     */
    public function __construct(LoaderInterface $loader = null)
    {
        $this->loader = $loader;
    }

    /**
     * {@inheritdoc}
     *
     * If the method was called with the same class name (or an object of that
     * class) before, the same metadata instance is returned.
     *
     * If the factory was configured with a cache, this method will first look
     * for an existing metadata instance in the cache. If an existing instance
     * is found, it will be returned without further ado.
     *
     * Otherwise, a new metadata instance is created. If the factory was
     * configured with a loader, the metadata is passed to the
     * {@link LoaderInterface::loadClassMetadata()} method for further
     * configuration. At last, the new object is returned.
     */
    public function getMetadataFor($value)
    {
        if (!is_object($value) && !is_string($value)) {
            throw new NoSuchMetadataException(sprintf('Cannot create metadata for non-objects. Got: %s', gettype($value)));
        }

        $class = ltrim(is_object($value) ? get_class($value) : $value, '\\');

        if (isset($this->loadedClasses[$class])) {
            return $this->loadedClasses[$class];
        }

        if (!class_exists($class) && !interface_exists($class)) {
            throw new NoSuchMetadataException(sprintf('The class or interface "%s" does not exist.', $class));
        }

        $metadata = new ClassMetadata($class);

        // Include metadata from the parent class
        if ($parent = $metadata->getReflectionClass()->getParentClass()) {
            $metadata->merge($this->getMetadataFor($parent->name));
        }

        // Include metadata from all implemented interfaces
        foreach ($metadata->getReflectionClass()->getInterfaces() as $interface) {
            $metadata->merge($this->getMetadataFor($interface->name));
        }

        if (null !== $this->loader) {
            $this->loader->loadClassMetadata($metadata);
        }

        return $this->loadedClasses[$class] = $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function hasMetadataFor($value)
    {
        if (!is_object($value) && !is_string($value)) {
            return false;
        }

        $class = ltrim(is_object($value) ? get_class($value) : $value, '\\');

        return class_exists($class) || interface_exists($class);
    }
}
