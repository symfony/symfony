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
 * Generates dumped PHP code of proxies via reflection.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class ProxyDumper implements DumperInterface
{
    /**
     * @var LazyLoadingValueHolderGenerator
     */
    private $proxyGenerator;

    /**
     * @var BaseGeneratorStrategy
     */
    private $classGenerator;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->proxyGenerator = new LazyLoadingValueHolderGenerator();
        $this->classGenerator = new BaseGeneratorStrategy();
    }

    /**
     * {@inheritdoc}
     */
    public function isProxyCandidate(Definition $definition)
    {
        return $definition->isLazy() && ($class = $definition->getClass()) && class_exists($class);
    }

    /**
     * {@inheritdoc}
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
<<<<<<< HEAD
            \$container = \$this;

            $instantiation new $proxyClass(
                function (&\$wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface \$proxy) use (\$container) {
                    \$wrappedInstance = \$container->$methodName(false);
=======

            $instantiation new $proxyClass(
                function (&\$wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface \$proxy) {
                    \$wrappedInstance = \$this->$methodName(false);
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

                    \$proxy->setProxyInitializer(null);

                    return true;
                }
            );
        }


EOF;
    }

    /**
     * {@inheritdoc}
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
     */
    private function getProxyClassName(Definition $definition)
    {
        return str_replace('\\', '', $definition->getClass()).'_'.spl_object_hash($definition);
    }
}
