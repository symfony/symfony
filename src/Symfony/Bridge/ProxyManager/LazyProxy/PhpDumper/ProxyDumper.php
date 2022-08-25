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

use Laminas\Code\Generator\ClassGenerator;
use ProxyManager\GeneratorStrategy\BaseGeneratorStrategy;
use Symfony\Bridge\ProxyManager\Internal\ProxyGenerator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\DumperInterface;

/**
 * Generates dumped PHP code of proxies via reflection.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 *
 * @final
 */
class ProxyDumper implements DumperInterface
{
    private string $salt;
    private ProxyGenerator $proxyGenerator;
    private BaseGeneratorStrategy $classGenerator;

    public function __construct(string $salt = '')
    {
        $this->salt = $salt;
        $this->proxyGenerator = new ProxyGenerator();
        $this->classGenerator = new BaseGeneratorStrategy();
    }

    public function isProxyCandidate(Definition $definition, bool &$asGhostObject = null): bool
    {
        $asGhostObject = false;

        return ($definition->isLazy() || $definition->hasTag('proxy')) && $this->proxyGenerator->getProxifiedClass($definition);
    }

    public function getProxyFactoryCode(Definition $definition, string $id, string $factoryCode): string
    {
        $instantiation = 'return';

        if ($definition->isShared()) {
            $instantiation .= sprintf(' $this->%s[%s] =', $definition->isPublic() && !$definition->isPrivate() ? 'services' : 'privates', var_export($id, true));
        }

        $proxifiedClass = new \ReflectionClass($this->proxyGenerator->getProxifiedClass($definition));
        $proxyClass = $this->getProxyClassName($proxifiedClass->name);

        return <<<EOF
        if (true === \$lazyLoad) {
            $instantiation \$this->createProxy('$proxyClass', function () {
                return \\$proxyClass::staticProxyConstructor(function (&\$wrappedInstance, \ProxyManager\Proxy\LazyLoadingInterface \$proxy) {
                    \$wrappedInstance = $factoryCode;

                    \$proxy->setProxyInitializer(null);

                    return true;
                });
            });
        }


EOF;
    }

    public function getProxyCode(Definition $definition): string
    {
        $code = $this->classGenerator->generate($this->generateProxyClass($definition));
        $code = preg_replace('/^(class [^ ]++ extends )([^\\\\])/', '$1\\\\$2', $code);

        return $code;
    }

    private function getProxyClassName(string $class): string
    {
        return preg_replace('/^.*\\\\/', '', $class).'_'.substr(hash('sha256', $class.$this->salt), -7);
    }

    private function generateProxyClass(Definition $definition): ClassGenerator
    {
        $class = $this->proxyGenerator->getProxifiedClass($definition);
        $generatedClass = new ClassGenerator($this->getProxyClassName($class));

        $this->proxyGenerator->generate(new \ReflectionClass($class), $generatedClass, [
            'fluentSafe' => $definition->hasTag('proxy'),
            'skipDestructor' => true,
        ]);

        return $generatedClass;
    }
}
