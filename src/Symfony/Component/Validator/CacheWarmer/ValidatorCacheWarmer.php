<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\CacheWarmer;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Validator\ValidatorBuilder;

/**
 * Warms up validator metadata.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class ValidatorCacheWarmer extends AbstractPhpFileCacheWarmer
{
    /**
     * @param string                           $phpArrayFile   The PHP file where metadata are cached
     * @param iterable<iterable<class-string>> $classProviders
     */
    public function __construct(
        private ValidatorBuilder $validatorBuilder,
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

        $loaders = $this->validatorBuilder->getLoaders();
        $metadataFactory = new LazyLoadingMetadataFactory(new LoaderChain($loaders), $arrayAdapter);

        $classes = [];
        foreach ($this->extractSupportedLoaders($loaders) as $loader) {
            array_push($classes, ...$loader->getMappedClasses());
        }
        foreach ($this->classProviders as $provider) {
            if (!is_iterable($provider)) {
                throw new \LogicException(\sprintf('Validator warmup class providers must be iterable, "%s" given.', get_debug_type($provider)));
            }

            foreach ($provider as $class) {
                $classes[] = $class;
            }
        }
        $classes = array_unique($classes);
        sort($classes);

        foreach ($classes as $class) {
            try {
                if ($metadataFactory->hasMetadataFor($class)) {
                    $metadataFactory->getMetadataFor($class);
                }
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
        // make sure we don't cache null values
        $values = array_filter($values, static fn ($val) => null !== $val);

        return parent::warmUpPhpArrayAdapter($phpArrayAdapter, $values);
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

if (!class_exists('Symfony\Bundle\FrameworkBundle\CacheWarmer\ValidatorCacheWarmer', false)) {
    class_alias(ValidatorCacheWarmer::class, 'Symfony\Bundle\FrameworkBundle\CacheWarmer\ValidatorCacheWarmer');
}
