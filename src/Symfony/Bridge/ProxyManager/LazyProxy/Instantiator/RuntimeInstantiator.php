<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\LazyProxy\Instantiator;

use ProxyManager\Configuration;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\GeneratorStrategy\EvaluatingGeneratorStrategy;
use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\InstantiatorInterface;

/**
 * Runtime lazy loading proxy generator.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class RuntimeInstantiator implements InstantiatorInterface
{
    /**
     * @var \ProxyManager\Factory\LazyLoadingValueHolderFactory
     */
    private $factory;

    /**
     * Constructor
     */
    public function __construct()
    {
        $config = new Configuration();

        $config->setGeneratorStrategy(new EvaluatingGeneratorStrategy());

        $this->factory = new LazyLoadingValueHolderFactory($config);
    }

    /**
     * {@inheritdoc}
     */
    public function instantiateProxy(ContainerInterface $container, Definition $definition, $id, $realInstantiator)
    {
        return $this->factory->createProxy(
            $definition->getClass(),
            function (&$wrappedInstance, LazyLoadingInterface $proxy, $method, $parameters) use ($realInstantiator, $definition) {
                if (!$wrappedInstance) {
                    $wrappedInstance = call_user_func($realInstantiator);

                    // If there is no lazy calls then we can disable the initializer
                    if (!$definition->getMethodLazyCalls()) {
                        $proxy->setProxyInitializer(null);

                        return true;
                    }
                }

                if ('__get' === $method) {
                    $method .= '::' . current($parameters);
                }

                $calls = $definition->getMethodLazyCalls();
                foreach ($calls as $call) {
                    $trigger = $call[2];
                    if (is_array($trigger)) {
                        $trigger = ('property' == key($trigger) ? '__get::' : '') . current($trigger);
                    } else if ('set' === substr($call[0], 0, 3)) {
                        $trigger = 'get' . substr($call[0], 3);   
                    }
                    if ($method == $trigger) {
                        call_user_func($call[3]);
                        break;
                    }
                }
            }
        );
    }
}
