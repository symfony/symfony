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
     * @param class-string[] $classes
     * @param string         $phpArrayFile The PHP file where property info is cached
     */
    public function __construct(
        private PropertyInfoExtractorInterface $extractor,
        private array $classes,
        string $phpArrayFile,
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

        foreach ($this->classes as $class) {
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
