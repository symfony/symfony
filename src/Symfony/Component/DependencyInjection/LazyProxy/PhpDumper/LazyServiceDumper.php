<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\LazyProxy\PhpDumper;

use Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\VarExporter\LazyGhostObjectInterface;
use Symfony\Component\VarExporter\LazyGhostObjectTrait;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class LazyServiceDumper implements DumperInterface
{
    public function __construct(
        private string $salt = '',
    ) {
    }

    public function isProxyCandidate(Definition $definition, bool &$asGhostObject = null): bool
    {
        $asGhostObject = false;

        if ($definition->hasTag('proxy')) {
            if (!$definition->isLazy()) {
                throw new InvalidArgumentException(sprintf('Invalid definition for service of class "%s": setting the "proxy" tag on a service requires it to be "lazy".', $definition->getClass()));
            }

            return true;
        }

        if (!$definition->isLazy()) {
            return false;
        }

        if (!($class = $definition->getClass()) || !(class_exists($class) || interface_exists($class, false))) {
            return false;
        }

        $class = new \ReflectionClass($class);

        if ($class->isFinal()) {
            throw new InvalidArgumentException(sprintf('Cannot make service of class "%s" lazy because the class is final.', $definition->getClass()));
        }

        if ($asGhostObject = !$class->isAbstract() && !$class->isInterface() && (\stdClass::class === $class->name || !$class->isInternal())) {
            while ($class = $class->getParentClass()) {
                if (!$asGhostObject = \stdClass::class === $class->name || !$class->isInternal()) {
                    break;
                }
            }
        }

        return true;
    }

    public function getProxyFactoryCode(Definition $definition, string $id, string $factoryCode): string
    {
        if ($dumper = $this->useProxyManager($definition)) {
            return $dumper->getProxyFactoryCode($definition, $id, $factoryCode);
        }

        $instantiation = 'return';

        if ($definition->isShared()) {
            $instantiation .= sprintf(' $this->%s[%s] =', $definition->isPublic() && !$definition->isPrivate() ? 'services' : 'privates', var_export($id, true));
        }

        $proxyClass = $this->getProxyClass($definition);

        if (preg_match('/^\$this->\w++\(\$proxy\)$/', $factoryCode)) {
            $factoryCode = substr_replace($factoryCode, '(...)', -8);
        } else {
            $factoryCode = sprintf('function ($proxy) { return %s; }', $factoryCode);
        }

        return <<<EOF
        if (true === \$lazyLoad) {
            $instantiation \$this->createProxy('$proxyClass', function () {
                return \\$proxyClass::createLazyGhostObject($factoryCode);
            });
        }


EOF;
    }

    public function getProxyCode(Definition $definition): string
    {
        if ($dumper = $this->useProxyManager($definition)) {
            return $dumper->getProxyCode($definition);
        }

        $proxyClass = $this->getProxyClass($definition);

        return sprintf(<<<EOF
            class %s extends \%s implements \%s
            {
                use \%s;
            }

            EOF,
            $proxyClass,
            $definition->getClass(),
            LazyGhostObjectInterface::class,
            LazyGhostObjectTrait::class
        );
    }

    public function getProxyClass(Definition $definition): string
    {
        $class = (new \ReflectionClass($definition->getClass()))->name;

        return preg_replace('/^.*\\\\/', '', $class).'_'.substr(hash('sha256', $this->salt.'+'.$class), -7);
    }

    public function useProxyManager(Definition $definition): ?ProxyDumper
    {
        if (!$this->isProxyCandidate($definition, $asGhostObject)) {
            throw new InvalidArgumentException(sprintf('Cannot instantiate lazy proxy for service of class "%s".', $definition->getClass()));
        }

        if ($asGhostObject) {
            return null;
        }

        if (!class_exists(ProxyDumper::class)) {
            throw new LogicException('You cannot use virtual proxies for lazy services as the ProxyManager bridge is not installed. Try running "composer require symfony/proxy-manager-bridge".');
        }

        return new ProxyDumper($this->salt);
    }
}
