<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Doctrine\Common\Annotations\AnnotationException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;

/**
 * Warms up XML and YAML serializer metadata.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class SerializerCacheWarmer extends AbstractPhpFileCacheWarmer
{
    private $loaders;

    /**
     * @param LoaderInterface[] $loaders      The serializer metadata loaders
     * @param string            $phpArrayFile The PHP file where metadata are cached
     */
    public function __construct(array $loaders, string $phpArrayFile)
    {
        if (2 < \func_num_args() && func_get_arg(2) instanceof CacheItemPoolInterface) {
            @trigger_error(sprintf('The CacheItemPoolInterface $fallbackPool argument of "%s()" is deprecated since Symfony 4.2, you should not pass it anymore.', __METHOD__), E_USER_DEPRECATED);
        }
        parent::__construct($phpArrayFile);
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
                } catch (AnnotationException $e) {
                    // ignore failing annotations
                } catch (\Exception $e) {
                    $this->ignoreAutoloadException($mappedClass, $e);
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
    private function extractSupportedLoaders(array $loaders): array
    {
        $supportedLoaders = [];

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
