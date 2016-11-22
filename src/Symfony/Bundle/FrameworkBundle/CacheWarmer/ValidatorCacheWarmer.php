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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Validator\Mapping\Cache\Psr6Cache;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Validator\ValidatorBuilderInterface;

/**
 * Warms up XML and YAML validator metadata.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class ValidatorCacheWarmer implements CacheWarmerInterface
{
    private $validatorBuilder;
    private $phpArrayFile;
    private $fallbackPool;

    /**
     * @param ValidatorBuilderInterface $validatorBuilder
     * @param string                    $phpArrayFile     The PHP file where metadata are cached.
     * @param CacheItemPoolInterface    $fallbackPool     The pool where runtime-discovered metadata are cached.
     */
    public function __construct(ValidatorBuilderInterface $validatorBuilder, $phpArrayFile, CacheItemPoolInterface $fallbackPool)
    {
        $this->validatorBuilder = $validatorBuilder;
        $this->phpArrayFile = $phpArrayFile;
        if (!$fallbackPool instanceof AdapterInterface) {
            $fallbackPool = new ProxyAdapter($fallbackPool);
        }
        $this->fallbackPool = $fallbackPool;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        if (!method_exists($this->validatorBuilder, 'getLoaders')) {
            return;
        }

        $adapter = new PhpArrayAdapter($this->phpArrayFile, $this->fallbackPool);
        $arrayPool = new ArrayAdapter(0, false);

        $loaders = $this->validatorBuilder->getLoaders();
        $metadataFactory = new LazyLoadingMetadataFactory(new LoaderChain($loaders), new Psr6Cache($arrayPool));

        foreach ($this->extractSupportedLoaders($loaders) as $loader) {
            foreach ($loader->getMappedClasses() as $mappedClass) {
                if ($metadataFactory->hasMetadataFor($mappedClass)) {
                    $metadataFactory->getMetadataFor($mappedClass);
                }
            }
        }

        $values = $arrayPool->getValues();
        $adapter->warmUp($values);

        foreach ($values as $k => $v) {
            $item = $this->fallbackPool->getItem($k);
            $this->fallbackPool->saveDeferred($item->set($v));
        }
        $this->fallbackPool->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function isOptional()
    {
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
                $supportedLoaders = array_merge($supportedLoaders, $this->extractSupportedLoaders($loader->getDelegatedLoaders()));
            }
        }

        return $supportedLoaders;
    }
}
