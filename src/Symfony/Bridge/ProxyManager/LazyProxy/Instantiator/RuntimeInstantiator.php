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
use ProxyManager\Factory\LazyLoadingGhostFactory;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\GeneratorStrategy\EvaluatingGeneratorStrategy;
use ProxyManager\Proxy\GhostObjectInterface;
use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Bridge\ProxyManager\Internal\LazyLoadingFactoryTrait;
use Symfony\Bridge\ProxyManager\Internal\ProxyGenerator;
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
    private Configuration $config;
    private ProxyGenerator $generator;

    public function __construct()
    {
        $this->config = new Configuration();
        $this->config->setGeneratorStrategy(new EvaluatingGeneratorStrategy());
        $this->generator = new ProxyGenerator();
    }

    /**
     * {@inheritdoc}
     */
    public function instantiateProxy(ContainerInterface $container, Definition $definition, string $id, callable $realInstantiator): object
    {
        $proxifiedClass = new \ReflectionClass($this->generator->getProxifiedClass($definition, $asGhostObject));
        $generator = $this->generator->asGhostObject($asGhostObject);

        if ($asGhostObject) {
            $factory = new class($this->config, $generator) extends LazyLoadingGhostFactory {
                use LazyLoadingFactoryTrait;
            };

            $initializer = static function (GhostObjectInterface $proxy, string $method, array $parameters, &$initializer, array $properties) use ($realInstantiator) {
                $instance = $realInstantiator($proxy);
                $initializer = null;

                if ($instance !== $proxy) {
                    throw new \LogicException(sprintf('A lazy initializer should return the ghost object proxy it was given as argument, but an instance of "%s" was returned.', get_debug_type($instance)));
                }

                return true;
            };
        } else {
            $factory = new class($this->config, $generator) extends LazyLoadingValueHolderFactory {
                use LazyLoadingFactoryTrait;
            };

            $initializer = static function (&$wrappedInstance, LazyLoadingInterface $proxy) use ($realInstantiator) {
                $wrappedInstance = $realInstantiator();
                $proxy->setProxyInitializer(null);

                return true;
            };
        }

        return $factory->createProxy($proxifiedClass->name, $initializer, [
            'fluentSafe' => $definition->hasTag('proxy'),
            'skipDestructor' => true,
        ]);
    }
}
