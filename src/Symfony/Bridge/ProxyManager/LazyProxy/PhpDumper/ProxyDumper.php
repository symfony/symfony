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
use Symfony\Component\DependencyInjection\Container;
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
        return $definition->isLazy() && ($class = $definition->getClass()) && (class_exists($class) || interface_exists($class));
    }

    /**
     * {@inheritdoc}
     */
    public function getProxyFactoryCode(Definition $definition, $id, $factoryCode = null)
    {
        $instantiation = 'return';

        if ($definition->isShared()) {
            $instantiation .= sprintf(' $this->%s[%s] =', method_exists(ContainerBuilder::class, 'addClassResource') || ($definition->isPublic() && !$definition->isPrivate()) ? 'services' : 'privates', var_export($id, true));
        }

        if (null === $factoryCode) {
            @trigger_error(sprintf('The "%s()" method expects a third argument defining the code to execute to construct your service since Symfony 3.4, providing it will be required in 4.0.', __METHOD__), E_USER_DEPRECATED);
            $factoryCode = '$this->get'.Container::camelize($id).'Service(false)';
        } elseif (false === strpos($factoryCode, '(')) {
            @trigger_error(sprintf('The "%s()" method expects its third argument to define the code to execute to construct your service since Symfony 3.4, providing it will be required in 4.0.', __METHOD__), E_USER_DEPRECATED);
            $factoryCode = "\$this->$factoryCode(false)";
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
        $code = $this->classGenerator->generate($this->generateProxyClass($definition));

        $code = preg_replace(
            '/(\$this->initializer[0-9a-f]++) && \1->__invoke\(\$this->(valueHolder[0-9a-f]++), (.*?), \1\);/',
            '$1 && ($1->__invoke(\$$2, $3, $1) || 1) && $this->$2 = \$$2;',
            $code
        );

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

    /**
     * @return string
     */
    private static function getProxyManagerVersion()
    {
        if (!class_exists(Version::class)) {
            return '0.0.1';
        }

        return \defined(Version::class.'::VERSION') ? Version::VERSION : Version::getVersion();
    }

    /**
     * Produces the proxy class name for the given definition.
     *
     * @return string
     */
    private function getProxyClassName(Definition $definition)
    {
        return preg_replace('/^.*\\\\/', '', $definition->getClass()).'_'.$this->getIdentifierSuffix($definition);
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

    /**
     * @return string
     */
    private function getIdentifierSuffix(Definition $definition)
    {
        return substr(hash('sha256', $definition->getClass().$this->salt), -7);
    }
}
