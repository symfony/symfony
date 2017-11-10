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
    private $salt;
    private $proxyGenerator;
    private $classGenerator;

    /**
     * @param string $salt
     */
    public function __construct($salt = '')
    {
        $this->salt = $salt;
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

        if ($definition->isShared()) {
            $instantiation .= " \$this->services['$id'] =";

            if (defined('Symfony\Component\DependencyInjection\ContainerInterface::SCOPE_CONTAINER') && ContainerInterface::SCOPE_CONTAINER !== $scope = $definition->getScope(false)) {
                $instantiation .= " \$this->scopedServices['$scope']['$id'] =";
            }
        }

        $methodName = 'get'.Container::camelize($id).'Service';
        $proxyClass = $this->getProxyClassName($definition);

        $generatedClass = $this->generateProxyClass($definition);

        $constructorCall = $generatedClass->hasMethod('staticProxyConstructor')
            ? $proxyClass.'::staticProxyConstructor'
            : 'new '.$proxyClass;

        return <<<EOF
        if (\$lazyLoad) {
            \$container = \$this;

            $instantiation $constructorCall(
                function (&\$wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface \$proxy) use (\$container) {
                    \$wrappedInstance = \$container->$methodName(false);

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
        return preg_replace(
            '/(\$this->initializer[0-9a-f]++) && \1->__invoke\(\$this->(valueHolder[0-9a-f]++), (.*?), \1\);/',
            '$1 && ($1->__invoke(\$$2, $3, $1) || 1) && $this->$2 = \$$2;',
            $this->classGenerator->generate($this->generateProxyClass($definition))
        );
    }

    /**
     * Produces the proxy class name for the given definition.
     *
     * @return string
     */
    private function getProxyClassName(Definition $definition)
    {
        return str_replace('\\', '', $definition->getClass()).'_'.spl_object_hash($definition).$this->salt;
    }

    /**
     * @return ClassGenerator
     */
    private function generateProxyClass(Definition $definition)
    {
        $generatedClass = new ClassGenerator($this->getProxyClassName($definition));

        $this->proxyGenerator->generate(new \ReflectionClass($definition->getClass()), $generatedClass);

        return $generatedClass;
    }
}
