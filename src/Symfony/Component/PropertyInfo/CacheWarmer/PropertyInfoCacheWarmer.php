<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\CacheWarmer;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyNameExtractorInterface;

/**
 * Warms up the property info of the classes known at build time.
 *
 * @internal
 */
final class PropertyInfoCacheWarmer extends AbstractPhpFileCacheWarmer
{
    /**
     * @param class-string[]                   $classes
     * @param string                           $phpArrayFile   The PHP file where property info is cached
     * @param iterable<iterable<class-string>> $classProviders
     */
    public function __construct(
        private PropertyInfoExtractorInterface $extractor,
        private array $classes,
        string $phpArrayFile,
        private iterable $classProviders = [],
    ) {
        parent::__construct($phpArrayFile);
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        if (!$buildDir) {
            return false;
        }

        // the same keys as at runtime, since the values are computed through the same caching extractor
        $extractor = new PropertyInfoCacheExtractor($this->extractor, $arrayAdapter);

        $classes = $this->classes;
        foreach ($this->classProviders as $provider) {
            if (!is_iterable($provider)) {
                throw new \LogicException(\sprintf('Property info warmup class providers must be iterable, "%s" given.', get_debug_type($provider)));
            }

            foreach ($provider as $class) {
                $classes[] = $class;
            }
        }
        $classes = array_unique($classes);
        sort($classes);

        foreach ($classes as $class) {
            try {
                foreach ($extractor->getProperties($class) ?? [] as $property) {
                    $extractor->isReadable($class, $property);
                    $extractor->isWritable($class, $property);
                    $extractor->getType($class, $property);
                }

                if ($this->extractor instanceof PropertyNameExtractorInterface) {
                    foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                        $extractor->getPropertyName($class, $method->name);
                    }
                }
            } catch (\Exception $e) {
                $this->ignoreAutoloadException($class, $e);
            }
        }

        return true;
    }
}
