<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\CacheWarmer;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\ObjectMapper\Metadata\CacheClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\CachePropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\CachePropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyNameCollectionFactory;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\VarExporter\Exception\ExceptionInterface as VarExporterExceptionInterface;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Warms up the mapping metadata of every class pair declared with #[Map].
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ObjectMapperCacheWarmer extends AbstractPhpFileCacheWarmer
{
    /**
     * @param array<class-string, list<class-string>> $classMap     Source class to its target classes
     * @param string                                  $phpArrayFile The PHP file where metadata are cached
     */
    public function __construct(
        private readonly array $classMap,
        string $phpArrayFile,
        private readonly ObjectMapperMetadataFactoryInterface $metadataFactory,
        private readonly ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
        parent::__construct($phpArrayFile);
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        if (!$buildDir) {
            return false;
        }

        $classMetadataFactory = new ReflectionClassMetadataFactory($this->metadataFactory);
        $propertyNameCollectionFactory = new ReflectionPropertyNameCollectionFactory();
        $propertyMetadataFactory = new ReflectionPropertyMetadataFactory($this->metadataFactory, $classMetadataFactory, $this->propertyAccessor);

        $cachedClassMetadataFactory = new CacheClassMetadataFactory($classMetadataFactory, $arrayAdapter);
        $cachedPropertyNameCollectionFactory = new CachePropertyNameCollectionFactory($propertyNameCollectionFactory, $arrayAdapter);
        $cachedPropertyMetadataFactory = new CachePropertyMetadataFactory($propertyMetadataFactory, $arrayAdapter, $propertyNameCollectionFactory);

        foreach ($this->classMap as $sourceClass => $targetClasses) {
            foreach ($targetClasses as $targetClass) {
                try {
                    if (!$source = $this->instantiate($sourceClass)) {
                        continue;
                    }
                    if (!$target = $this->instantiate($targetClass)) {
                        continue;
                    }

                    $classMetadata = $classMetadataFactory->create($source, $target);
                    $propertyNames = [...$propertyNameCollectionFactory->create($source), ...$propertyNameCollectionFactory->create($target)];
                    $propertyMetadata = array_map(static fn (string $property) => $propertyMetadataFactory->create($source, $target, $property), array_unique($propertyNames));

                    VarExporter::export([$classMetadata, $propertyMetadata]);
                } catch (VarExporterExceptionInterface) {
                    continue;
                } catch (\Exception $e) {
                    $this->ignoreAutoloadException($sourceClass, $e);

                    continue;
                }

                $cachedClassMetadataFactory->create($source, $target);
                $cachedPropertyNameCollectionFactory->create($source);
                $cachedPropertyNameCollectionFactory->create($target);
                if ($propertyNames) {
                    $cachedPropertyMetadataFactory->create($source, $target, $propertyNames[0]);
                }
            }
        }

        return true;
    }

    private function instantiate(string $class): ?object
    {
        $refl = new \ReflectionClass($class);

        if ($refl->isEnum()) {
            return $class::cases()[0] ?? null;
        }

        if ($refl->isAbstract() || $refl->isInterface()) {
            return null;
        }

        try {
            return $refl->newInstanceWithoutConstructor();
        } catch (\ReflectionException) {
            return null;
        }
    }
}
