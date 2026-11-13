<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\CacheWarmer;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;

/**
 * Warms up serializer metadata.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class SerializerCacheWarmer extends AbstractPhpFileCacheWarmer
{
    /**
     * @param LoaderInterface[]                $loaders        The serializer metadata loaders
     * @param string                           $phpArrayFile   The PHP file where metadata are cached
     * @param iterable<iterable<class-string>> $classProviders
     */
    public function __construct(
        private array $loaders,
        string $phpArrayFile,
        private iterable $classProviders = [],
        private ?CacheItemPoolInterface $fallbackPool = null,
    ) {
        parent::__construct($phpArrayFile);
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        if (!$buildDir) {
            return false;
        }
        $metadataFactory = new CacheClassMetadataFactory(new ClassMetadataFactory(new LoaderChain($this->loaders)), $arrayAdapter);

        $classes = [];
        foreach ($this->extractSupportedLoaders($this->loaders) as $loader) {
            array_push($classes, ...$loader->getMappedClasses());
        }
        foreach ($this->classProviders as $provider) {
            if (!is_iterable($provider)) {
                throw new \LogicException(\sprintf('Serializer warmup class providers must be iterable, "%s" given.', get_debug_type($provider)));
            }

            foreach ($provider as $class) {
                $classes[] = $class;
            }
        }
        $classes = array_unique($classes);
        sort($classes);

        foreach ($classes as $class) {
            try {
                $metadataFactory->getMetadataFor($class);
            } catch (\Exception $e) {
                $this->ignoreAutoloadException($class, $e);
            }
        }

        return true;
    }

    /**
     * @return string[] A list of classes to preload on PHP 7.4+
     */
    protected function warmUpPhpArrayAdapter(PhpArrayAdapter $phpArrayAdapter, array $values): array
    {
        $preload = parent::warmUpPhpArrayAdapter($phpArrayAdapter, $values);
        $this->fallbackPool?->deleteItems(array_keys($values));

        return $preload;
    }

    /**
     * @param LoaderInterface[] $loaders
     *
     * @return list<XmlFileLoader|YamlFileLoader|AttributeLoader>
     */
    private function extractSupportedLoaders(array $loaders): array
    {
        $supportedLoaders = [];

        foreach ($loaders as $loader) {
            if (method_exists($loader, 'getMappedClasses')) {
                $supportedLoaders[] = $loader;
            } elseif ($loader instanceof LoaderChain) {
                $supportedLoaders = array_merge($supportedLoaders, $this->extractSupportedLoaders($loader->getLoaders()));
            }
        }

        return $supportedLoaders;
    }
}

if (!class_exists('Symfony\Bundle\FrameworkBundle\CacheWarmer\SerializerCacheWarmer', false)) {
    class_alias(SerializerCacheWarmer::class, 'Symfony\Bundle\FrameworkBundle\CacheWarmer\SerializerCacheWarmer');
}
