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
use ProxyManager\Version;
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
    public function isProxyCandidate(Definition $definition): bool
    {
        return ($definition->isLazy() || $definition->hasTag('proxy')) && $this->proxyGenerator->getProxifiedClass($definition);
    }

    /**
     * {@inheritdoc}
     */
    public function getProxyFactoryCode(Definition $definition, string $id, string $factoryCode): string
    {
        $instantiation = 'return';

        if ($definition->isShared()) {
            $instantiation .= sprintf(' $this->%s[%s] =', $definition->isPublic() && !$definition->isPrivate() ? 'services' : 'privates', var_export($id, true));
        }

        $proxyClass = $this->getProxyClassName($definition);

        return <<<EOF
        if (\$lazyLoad) {
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

    /**
     * {@inheritdoc}
     */
    public function getProxyCode(Definition $definition): string
    {
        $code = $this->classGenerator->generate($this->generateProxyClass($definition));

        if (version_compare(self::getProxyManagerVersion(), '2.2', '<')) {
            $code = preg_replace(
                '/((?:\$(?:this|initializer|instance)->)?(?:publicProperties|initializer|valueHolder))[0-9a-f]++/',
                '${1}'.$this->getIdentifierSuffix($definition),
                $code
            );
        }

        if (version_compare(self::getProxyManagerVersion(), '2.5', '<')) {
            $code = preg_replace('/ \\\\Closure::bind\(function ((?:& )?\(\$instance(?:, \$value)?\))/', ' \Closure::bind(static function \1', $code);
        }

        return $code;
    }

    private static function getProxyManagerVersion(): string
    {
        if (!class_exists(Version::class)) {
            return '0.0.1';
        }

        return \defined(Version::class.'::VERSION') ? Version::VERSION : Version::getVersion();
    }

    /**
     * Produces the proxy class name for the given definition.
     */
    private function getProxyClassName(Definition $definition): string
    {
        $class = $this->proxyGenerator->getProxifiedClass($definition);

        return preg_replace('/^.*\\\\/', '', $class).'_'.$this->getIdentifierSuffix($definition);
    }

    private function generateProxyClass(Definition $definition): ClassGenerator
    {
        $generatedClass = new ClassGenerator($this->getProxyClassName($definition));
        $class = $this->proxyGenerator->getProxifiedClass($definition);

        $this->proxyGenerator->setFluentSafe($definition->hasTag('proxy'));
        $this->proxyGenerator->generate(new \ReflectionClass($class), $generatedClass);

        return $generatedClass;
    }

    private function getIdentifierSuffix(Definition $definition): string
    {
        $class = $this->proxyGenerator->getProxifiedClass($definition);

        return substr(hash('sha256', $class.$this->salt), -7);
    }
}
