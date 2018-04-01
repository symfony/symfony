<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\CacheWarmer;

use Doctrine\Common\Annotations\AnnotationException;
use Psr\Cache\CacheItemPoolInterface;
use Symphony\Component\Cache\Adapter\ArrayAdapter;
use Symphony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symphony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symphony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symphony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symphony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symphony\Component\Serializer\Mapping\Loader\YamlFileLoader;

/**
 * Warms up XML and YAML serializer metadata.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class SerializerCacheWarmer extends AbstractPhpFileCacheWarmer
{
    private $loaders;

    /**
     * @param LoaderInterface[]      $loaders      The serializer metadata loaders
     * @param string                 $phpArrayFile The PHP file where metadata are cached
     * @param CacheItemPoolInterface $fallbackPool The pool where runtime-discovered metadata are cached
     */
    public function __construct(array $loaders, string $phpArrayFile, CacheItemPoolInterface $fallbackPool)
    {
        parent::__construct($phpArrayFile, $fallbackPool);
        $this->loaders = $loaders;
    }

    /**
     * {@inheritdoc}
     */
    protected function doWarmUp($cacheDir, ArrayAdapter $arrayAdapter)
    {
        if (!class_exists(CacheClassMetadataFactory::class) || !method_exists(XmlFileLoader::class, 'getMappedClasses') || !method_exists(YamlFileLoader::class, 'getMappedClasses')) {
            return false;
        }

        $metadataFactory = new CacheClassMetadataFactory(new ClassMetadataFactory(new LoaderChain($this->loaders)), $arrayAdapter);

        foreach ($this->extractSupportedLoaders($this->loaders) as $loader) {
            foreach ($loader->getMappedClasses() as $mappedClass) {
                try {
                    $metadataFactory->getMetadataFor($mappedClass);
                } catch (\ReflectionException $e) {
                    // ignore failing reflection
                } catch (AnnotationException $e) {
                    // ignore failing annotations
                }
            }
        }

        return true;
    }

    /**
     * @param LoaderInterface[] $loaders
     *
     * @return XmlFileLoader[]|YamlFileLoader[]
     */
    private function extractSupportedLoaders(array $loaders)
    {
        $supportedLoaders = array();

        foreach ($loaders as $loader) {
            if ($loader instanceof XmlFileLoader || $loader instanceof YamlFileLoader) {
                $supportedLoaders[] = $loader;
            } elseif ($loader instanceof LoaderChain) {
                $supportedLoaders = array_merge($supportedLoaders, $this->extractSupportedLoaders($loader->getLoaders()));
            }
        }

        return $supportedLoaders;
    }
}
