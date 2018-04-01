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
use Symphony\Component\Cache\Adapter\PhpArrayAdapter;
use Symphony\Component\Validator\Mapping\Cache\Psr6Cache;
use Symphony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symphony\Component\Validator\Mapping\Loader\LoaderChain;
use Symphony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symphony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symphony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symphony\Component\Validator\ValidatorBuilderInterface;

/**
 * Warms up XML and YAML validator metadata.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class ValidatorCacheWarmer extends AbstractPhpFileCacheWarmer
{
    private $validatorBuilder;

    /**
     * @param ValidatorBuilderInterface $validatorBuilder
     * @param string                    $phpArrayFile     The PHP file where metadata are cached
     * @param CacheItemPoolInterface    $fallbackPool     The pool where runtime-discovered metadata are cached
     */
    public function __construct(ValidatorBuilderInterface $validatorBuilder, string $phpArrayFile, CacheItemPoolInterface $fallbackPool)
    {
        parent::__construct($phpArrayFile, $fallbackPool);
        $this->validatorBuilder = $validatorBuilder;
    }

    /**
     * {@inheritdoc}
     */
    protected function doWarmUp($cacheDir, ArrayAdapter $arrayAdapter)
    {
        if (!method_exists($this->validatorBuilder, 'getLoaders')) {
            return false;
        }

        $loaders = $this->validatorBuilder->getLoaders();
        $metadataFactory = new LazyLoadingMetadataFactory(new LoaderChain($loaders), new Psr6Cache($arrayAdapter));

        foreach ($this->extractSupportedLoaders($loaders) as $loader) {
            foreach ($loader->getMappedClasses() as $mappedClass) {
                try {
                    if ($metadataFactory->hasMetadataFor($mappedClass)) {
                        $metadataFactory->getMetadataFor($mappedClass);
                    }
                } catch (\ReflectionException $e) {
                    // ignore failing reflection
                } catch (AnnotationException $e) {
                    // ignore failing annotations
                }
            }
        }

        return true;
    }

    protected function warmUpPhpArrayAdapter(PhpArrayAdapter $phpArrayAdapter, array $values)
    {
        // make sure we don't cache null values
        parent::warmUpPhpArrayAdapter($phpArrayAdapter, array_filter($values));
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
