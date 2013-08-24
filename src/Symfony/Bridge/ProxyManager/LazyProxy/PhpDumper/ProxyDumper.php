<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper;

use ProxyManager\Generator\ClassGenerator;
use ProxyManager\GeneratorStrategy\BaseGeneratorStrategy;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolderGenerator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\DumperInterface;

/**
 * Generates dumped php code of proxies via reflection.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 *
 * @since v2.3.0
 */
class ProxyDumper implements DumperInterface
{
    /**
     * @var \ProxyManager\ProxyGenerator\LazyLoadingValueHolderGenerator
     */
    private $proxyGenerator;

    /**
     * @var \ProxyManager\GeneratorStrategy\BaseGeneratorStrategy
     */
    private $classGenerator;

    /**
     * Constructor
     *
     * @since v2.3.0
     */
    public function __construct()
    {
        $this->proxyGenerator = new LazyLoadingValueHolderGenerator();
        $this->classGenerator = new BaseGeneratorStrategy();
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function isProxyCandidate(Definition $definition)
    {
        return $definition->isLazy() && ($class = $definition->getClass()) && class_exists($class);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function getProxyFactoryCode(Definition $definition, $id)
    {
        $instantiation = 'return';

        if (ContainerInterface::SCOPE_CONTAINER === $definition->getScope()) {
            $instantiation .= " \$this->services['$id'] =";
        } elseif (ContainerInterface::SCOPE_PROTOTYPE !== $scope = $definition->getScope()) {
            $instantiation .= " \$this->services['$id'] = \$this->scopedServices['$scope']['$id'] =";
        }

        $methodName = 'get'.Container::camelize($id).'Service';
        $proxyClass = $this->getProxyClassName($definition);

        return <<<EOF
        if (\$lazyLoad) {
            \$container = \$this;

            $instantiation new $proxyClass(
                function (&\$wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface \$proxy) use (\$container) {
                    \$proxy->setProxyInitializer(null);

                    \$wrappedInstance = \$container->$methodName(false);

                    return true;
                }
            );
        }


EOF;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function getProxyCode(Definition $definition)
    {
        $generatedClass = new ClassGenerator($this->getProxyClassName($definition));

        $this->proxyGenerator->generate(new \ReflectionClass($definition->getClass()), $generatedClass);

        return $this->classGenerator->generate($generatedClass);
    }

    /**
     * Produces the proxy class name for the given definition.
     *
     * @param Definition $definition
     *
     * @return string
     *
     * @since v2.3.0
     */
    private function getProxyClassName(Definition $definition)
    {
        return str_replace('\\', '', $definition->getClass()).'_'.spl_object_hash($definition);
    }
}
