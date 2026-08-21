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
use Symfony\Component\Validator\Constraints\DisableAutoMapping;
use Symfony\Component\Validator\Constraints\EnableAutoMapping;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\MemberMetadata;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadata;

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

    public function __construct(?LoaderInterface $loader = null, ?CacheItemPoolInterface $cache = null)
    {
        $this->loader = $loader;
        $this->cache = $cache;
    }

    /**
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
    public function getMetadataFor(mixed $value): MetadataInterface
    {
        if (!\is_object($value) && !\is_string($value)) {
            throw new NoSuchMetadataException(\sprintf('Cannot create metadata for non-objects. Got: "%s".', get_debug_type($value)));
        }

        $class = ltrim(\is_object($value) ? $value::class : $value, '\\');

        if (isset($this->loadedClasses[$class])) {
            return $this->loadedClasses[$class];
        }

        if (!class_exists($class) && !interface_exists($class, false)) {
            throw new NoSuchMetadataException(\sprintf('The class or interface "%s" does not exist.', $class));
        }

        $cacheItem = $this->cache?->getItem($this->escapeClassName($class));
        if ($cacheItem?->isHit()) {
            $metadata = $cacheItem->get();

            // Include constraints from the parent class
            $this->mergeConstraints($metadata);

            return $this->loadedClasses[$class] = $metadata;
        }

        $metadata = new ClassMetadata($class);

        if (null !== $this->loader) {
            // Loaders that map constraints automatically need to know about the strategies declared in parent classes
            $placeholders = $this->inheritAutoMappingStrategies($metadata);

            $this->loader->loadClassMetadata($metadata);

            $this->removeUnusedPlaceholders($metadata, $placeholders);
        }

        if (null !== $cacheItem) {
            $this->cache->save($cacheItem->set($metadata));
        }

        // Include constraints from the parent class
        $this->mergeConstraints($metadata);

        return $this->loadedClasses[$class] = $metadata;
    }

    private function mergeConstraints(ClassMetadata $metadata): void
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

    public function hasMetadataFor(mixed $value): bool
    {
        if (!\is_object($value) && !\is_string($value)) {
            return false;
        }

        $class = ltrim(\is_object($value) ? $value::class : $value, '\\');

        return class_exists($class) || interface_exists($class, false);
    }

    /**
     * Copies the auto-mapping strategies declared on the parent's properties.
     *
     * @return array<string, array{PropertyMetadata, int}> the property metadata created to carry them
     */
    private function inheritAutoMappingStrategies(ClassMetadata $metadata): array
    {
        if (!$parent = $metadata->getReflectionClass()->getParentClass()) {
            return [];
        }

        $parentMetadata = $this->getMetadataFor($parent->name);

        if (!$parentMetadata instanceof ClassMetadata) {
            return [];
        }

        $class = $metadata->getClassName();
        $placeholders = [];

        foreach ($parentMetadata->getConstrainedProperties() as $property) {
            foreach ($parentMetadata->getPropertyMetadata($property) as $member) {
                if (!$member instanceof PropertyMetadata || $member->isPrivate($class)) {
                    continue;
                }

                if (AutoMappingStrategy::NONE !== $strategy = $member->getAutoMappingStrategy()) {
                    $metadata->addPropertyConstraint($property, AutoMappingStrategy::DISABLED === $strategy ? new DisableAutoMapping() : new EnableAutoMapping());
                    $placeholders[$property] = [$metadata->properties[$property], $strategy];
                }

                // The first property metadata is the one declared closest to the class, so it wins
                break;
            }
        }

        return $placeholders;
    }

    /**
     * Drops the placeholders the loaders left untouched, and moves the remaining
     * ones last, where merging the parent's metadata would have put them.
     *
     * @param array<string, array{PropertyMetadata, int}> $placeholders
     */
    private function removeUnusedPlaceholders(ClassMetadata $metadata, array $placeholders): void
    {
        foreach ($placeholders as $property => [$placeholder, $strategy]) {
            if (!$placeholder->getConstraints() && $strategy === $placeholder->getAutoMappingStrategy() && CascadingStrategy::NONE === $placeholder->getCascadingStrategy()) {
                $metadata->members[$property] = array_values(array_filter($metadata->members[$property], static fn (MemberMetadata $member) => $member !== $placeholder));

                if ($placeholder === ($metadata->properties[$property] ?? null)) {
                    unset($metadata->properties[$property]);
                }
            }

            $members = $metadata->members[$property];
            unset($metadata->members[$property]);

            if ($members) {
                $metadata->members[$property] = $members;
            }
        }
    }

    /**
     * Replaces backslashes by dots in a class name.
     */
    private function escapeClassName(string $class): string
    {
        if (str_contains($class, '@')) {
            // anonymous class: replace all PSR6-reserved characters
            return str_replace(["\0", '\\', '/', '@', ':', '{', '}', '(', ')'], '.', $class);
        }

        return str_replace('\\', '.', $class);
    }
}
