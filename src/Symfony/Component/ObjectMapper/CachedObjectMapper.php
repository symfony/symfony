<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Internal\MappingCacheGenerator;
use Symfony\Component\ObjectMapper\Internal\MappingCacheGeneratorInterface;
use Symfony\Component\ObjectMapper\Internal\MappingCacheTrait;
use Symfony\Component\ObjectMapper\Internal\ObjectMapperTrait;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * Cached Object to object mapper that generates and caches compiled mapping functions.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class CachedObjectMapper implements ObjectMapperInterface, ObjectMapperAwareInterface
{
    use MappingCacheTrait;
    use ObjectMapperAwareTrait;
    use ObjectMapperTrait;

    private array $cachedMappings = [];
    private ?\SplObjectStorage $objectMap = null;

    public function __construct(
        private readonly string $cacheDir,
        ObjectMapperMetadataFactoryInterface $metadataFactory = new ReflectionObjectMapperMetadataFactory(),
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?ContainerInterface $transformCallableLocator = null,
        ?ContainerInterface $conditionCallableLocator = null,
        ?ObjectMapperInterface $objectMapper = null,
        ?MappingCacheGeneratorInterface $generator = null,
    ) {
        $this->metadataFactory = $metadataFactory;
        $this->propertyAccessor = $propertyAccessor;
        $this->transformCallableLocator = $transformCallableLocator;
        $this->conditionCallableLocator = $conditionCallableLocator;
        $this->objectMapper = $objectMapper;
        $this->generator = $generator ?? new MappingCacheGenerator($metadataFactory);
    }

    public function map(object $source, object|string|null $target = null): object
    {
        $sourceClass = $source::class;
        $targetClass = $this->resolveTargetClass($source, $target);

        if (!$targetClass) {
            throw new MappingException(\sprintf('Mapping target not found for source "%s".', get_debug_type($source)));
        }

        if (\is_string($target) && !class_exists($target)) {
            throw new MappingException(\sprintf('Mapping target class "%s" does not exist for source "%s".', $target, get_debug_type($source)));
        }

        $cacheKey = hash('xxh128', $sourceClass.'-to-'.$targetClass);

        if (!isset($this->cachedMappings[$cacheKey])) {
            $cacheFile = $this->getCacheFile($sourceClass, $targetClass);

            if (!is_file($cacheFile)) {
                $this->writeCacheFile($cacheFile, $sourceClass, $targetClass);
            }

            $this->cachedMappings[$cacheKey] = require $cacheFile;
        }

        $mappingFunction = $this->cachedMappings[$cacheKey];
        $mappingToObject = \is_object($target);
        $targetObject = \is_object($target) ? $target : (new \ReflectionClass($targetClass))->newInstanceWithoutConstructor();

        if ($source instanceof LazyObjectInterface) {
            $source->initializeLazyObject();
        } elseif (\PHP_VERSION_ID >= 80400) {
            (new \ReflectionClass($source))->initializeLazyObject($source);
        }

        $objectMapInitialized = null === $this->objectMap;
        if ($objectMapInitialized) {
            $this->objectMap = new \SplObjectStorage();
        }

        $mappedTarget = $mappingFunction(
            $source,
            $targetObject,
            $this->objectMapper ?? $this,
            $this->metadataFactory,
            $this->objectMap,
            $this->propertyAccessor,
            $this->transformCallableLocator,
            $this->conditionCallableLocator,
            $mappingToObject
        );

        if ($objectMapInitialized) {
            $this->objectMap = null;
        }

        return $mappedTarget;
    }

    private function resolveTargetClass(object $source, object|string|null $target): ?string
    {
        if (\is_string($target)) {
            return $target;
        }

        if (\is_object($target)) {
            return $target::class;
        }

        $metadata = $this->metadataFactory->create($source);
        $map = $this->getMapTarget($metadata, null, $source, null);
        if (\is_string($map?->target) && class_exists($map->target)) {
            return $map->target;
        }

        return null;
    }
}
