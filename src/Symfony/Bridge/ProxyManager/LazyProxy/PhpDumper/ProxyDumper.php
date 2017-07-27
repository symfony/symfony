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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\DumperInterface;

/**
 * Generates dumped PHP code of proxies via reflection.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 *
 * @final since version 3.3
 */
class ProxyDumper implements DumperInterface
{
    /**
     * @var string
     */
    private $salt;

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
     *
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
    public function getProxyFactoryCode(Definition $definition, $id, $factoryCode = null)
    {
        $instantiation = 'return';

        if ($definition->isShared()) {
            $instantiation .= " \$this->services['$id'] =";
        }

        if (null === $factoryCode) {
            @trigger_error(sprintf('The "%s()" method expects a third argument defining the code to execute to construct your service since version 3.4, providing it will be required in 4.0.', __METHOD__), E_USER_DEPRECATED);
            $factoryCode = '$this->get'.Container::camelize($id).'Service(false)';
        } elseif (false === strpos($factoryCode, '(')) {
            @trigger_error(sprintf('The "%s()" method expects its third argument to define the code to execute to construct your service since version 3.4, providing it will be required in 4.0.', __METHOD__), E_USER_DEPRECATED);
            $factoryCode = "\$this->$factoryCode(false)";
        }
        $proxyClass = $this->getProxyClassName($definition);

        $generatedClass = $this->generateProxyClass($definition);

        $constructorCall = $generatedClass->hasMethod('staticProxyConstructor')
            ? $proxyClass.'::staticProxyConstructor'
            : 'new '.$proxyClass;

        return <<<EOF
        if (\$lazyLoad) {

            $instantiation $constructorCall(
                function (&\$wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface \$proxy) {
                    \$wrappedInstance = $factoryCode;

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
        return $this->classGenerator->generate($this->generateProxyClass($definition));
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
