<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\ProxyManager\LazyProxy\Instantiator;

use ProxyManager\Configuration;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\GeneratorStrategy\EvaluatingGeneratorStrategy;
use ProxyManager\Proxy\LazyLoadingInterface;
use Symphony\Component\DependencyInjection\ContainerInterface;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\LazyProxy\Instantiator\InstantiatorInterface;

/**
 * Runtime lazy loading proxy generator.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class RuntimeInstantiator implements InstantiatorInterface
{
    /**
     * @var LazyLoadingValueHolderFactory
     */
    private $factory;

    public function __construct()
    {
        $config = new Configuration();
        $config->setGeneratorStrategy(new EvaluatingGeneratorStrategy());

        if (method_exists('ProxyManager\Version', 'getVersion')) {
            $this->factory = new LazyLoadingValueHolderFactoryV2($config);
        } else {
            $this->factory = new LazyLoadingValueHolderFactoryV1($config);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function instantiateProxy(ContainerInterface $container, Definition $definition, $id, $realInstantiator)
    {
        return $this->factory->createProxy(
            $definition->getClass(),
            function (&$wrappedInstance, LazyLoadingInterface $proxy) use ($realInstantiator) {
                $wrappedInstance = call_user_func($realInstantiator);

                $proxy->setProxyInitializer(null);

                return true;
            }
        );
    }
}
