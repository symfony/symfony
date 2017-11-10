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
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
    private $salt;
    private $proxyGenerator;
    private $classGenerator;

    public function __construct(string $salt = '')
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
            $instantiation .= sprintf(' $this->%s[\'%s\'] =', $definition->isPublic() || !method_exists(ContainerBuilder::class, 'addClassResource') ? 'services' : 'privates', $id);
        }

        if (null === $factoryCode) {
            throw new \InvalidArgumentException(sprintf('Missing factory code to construct the service "%s".', $id));
        }

        $proxyClass = $this->getProxyClassName($definition);

        $hasStaticConstructor = $this->generateProxyClass($definition)->hasMethod('staticProxyConstructor');

        $constructorCall = sprintf($hasStaticConstructor ? '%s::staticProxyConstructor' : 'new %s', '\\'.$proxyClass);

        return <<<EOF
        if (\$lazyLoad) {
            $instantiation \$this->createProxy('$proxyClass', function () {
                return $constructorCall(function (&\$wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface \$proxy) {
                    \$wrappedInstance = $factoryCode;

                    \$proxy->setProxyInitializer(null);

                    return true;
                });
            });
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
        return preg_replace('/^.*\\\\/', '', $definition->getClass()).'_'.substr(hash('sha256', spl_object_hash($definition).$this->salt), -7);
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
