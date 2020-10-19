<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Factory;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Mapping\Cache\CacheInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

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
 * You can also optionally pass a {@link CacheInterface} instance to the
 * constructor. This cache will be used for persisting the generated metadata
 * between multiple PHP requests.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LazyLoadingMetadataFactory implements MetadataFactoryInterface
{
    protected $loader;
    protected $cache;

    /**
     * The loaded metadata, indexed by class name.
     *
     * @var ClassMetadata[]
     */
    protected $loadedClasses = [];

    /**
     * Creates a new metadata factory.
     *
     * @param CacheItemPoolInterface|null $cache The cache for persisting metadata
     *                                           between multiple PHP requests
     */
    public function __construct(LoaderInterface $loader = null, $cache = null)
    {
        if ($cache instanceof CacheInterface) {
            @trigger_error(sprintf('Passing a "%s" to "%s" is deprecated in Symfony 4.4 and will trigger a TypeError in 5.0. Please pass an implementation of "%s" instead.', \get_class($cache), __METHOD__, CacheItemPoolInterface::class), \E_USER_DEPRECATED);
        } elseif (!$cache instanceof CacheItemPoolInterface && null !== $cache) {
            throw new \TypeError(sprintf('Expected an instance of "%s", got "%s".', CacheItemPoolInterface::class, \is_object($cache) ? \get_class($cache) : \gettype($cache)));
        }

        $this->loader = $loader;
        $this->cache = $cache;
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
        if (!\is_object($value) && !\is_string($value)) {
            throw new NoSuchMetadataException(sprintf('Cannot create metadata for non-objects. Got: "%s".', \gettype($value)));
        }

        $class = ltrim(\is_object($value) ? \get_class($value) : $value, '\\');

        if (isset($this->loadedClasses[$class])) {
            return $this->loadedClasses[$class];
        }

        if (!class_exists($class) && !interface_exists($class, false)) {
            throw new NoSuchMetadataException(sprintf('The class or interface "%s" does not exist.', $class));
        }

        $cacheItem = null;
        if ($this->cache instanceof CacheInterface) {
            if ($metadata = $this->cache->read($class)) {
                // Include constraints from the parent class
                $this->mergeConstraints($metadata);

                return $this->loadedClasses[$class] = $metadata;
            }
        } elseif (null !== $this->cache) {
            $cacheItem = $this->cache->getItem($this->escapeClassName($class));
            if ($cacheItem->isHit()) {
                $metadata = $cacheItem->get();

                // Include constraints from the parent class
                $this->mergeConstraints($metadata);

                return $this->loadedClasses[$class] = $metadata;
            }
        }

        $metadata = new ClassMetadata($class);

        if (null !== $this->loader) {
            $this->loader->loadClassMetadata($metadata);
        }

        if ($this->cache instanceof CacheInterface) {
            $this->cache->write($metadata);
        } elseif (null !== $cacheItem) {
            $this->cache->save($cacheItem->set($metadata));
        }

        // Include constraints from the parent class
        $this->mergeConstraints($metadata);

        return $this->loadedClasses[$class] = $metadata;
    }

    private function mergeConstraints(ClassMetadata $metadata)
    {
        if ($metadata->getReflectionClass()->isInterface()) {
            return;
        }

        // Include constraints from the parent class
        if ($parent = $metadata->getReflectionClass()->getParentClass()) {
            $metadata->mergeConstraints($this->getMetadataFor($parent->name));
        }

        // Include constraints from all directly implemented interfaces
        foreach ($metadata->getReflectionClass()->getInterfaces() as $interface) {
            if ('Symfony\Component\Validator\GroupSequenceProviderInterface' === $interface->name) {
                continue;
            }

            if ($parent && \in_array($interface->getName(), $parent->getInterfaceNames(), true)) {
                continue;
            }

            $metadata->mergeConstraints($this->getMetadataFor($interface->name));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasMetadataFor($value)
    {
        if (!\is_object($value) && !\is_string($value)) {
            return false;
        }

        $class = ltrim(\is_object($value) ? \get_class($value) : $value, '\\');

        return class_exists($class) || interface_exists($class, false);
    }

    /**
     * Replaces backslashes by dots in a class name.
     */
    private function escapeClassName(string $class): string
    {
        if (false !== strpos($class, '@')) {
            // anonymous class: replace all PSR6-reserved characters
            return str_replace(["\0", '\\', '/', '@', ':', '{', '}', '(', ')'], '.', $class);
        }

        return str_replace('\\', '.', $class);
    }
}
