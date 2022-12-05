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
use Symfony\Bridge\ProxyManager\Internal\LazyLoadingFactoryTrait;
use Symfony\Bridge\ProxyManager\Internal\ProxyGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\InstantiatorInterface;

trigger_deprecation('symfony/proxy-manager-bridge', '6.3', 'The "symfony/proxy-manager-bridge" package is deprecated and can be removed from your dependencies.');

/**
 * Runtime lazy loading proxy generator.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 *
 * @deprecated since Symfony 6.3
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

    public function instantiateProxy(ContainerInterface $container, Definition $definition, string $id, callable $realInstantiator): object
    {
        $proxifiedClass = new \ReflectionClass($this->generator->getProxifiedClass($definition));

        $factory = new class($this->config, $this->generator) extends LazyLoadingValueHolderFactory {
            use LazyLoadingFactoryTrait;
        };

        $initializer = static function (&$wrappedInstance, LazyLoadingInterface $proxy) use ($realInstantiator) {
            $wrappedInstance = $realInstantiator();
            $proxy->setProxyInitializer(null);

            return true;
        };

        return $factory->createProxy($proxifiedClass->name, $initializer, [
            'fluentSafe' => $definition->hasTag('proxy'),
            'skipDestructor' => true,
        ]);
    }
}
