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

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\VarExporter\Exception\LogicException;
use Symfony\Component\VarExporter\ProxyHelper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class LazyServiceDumper implements DumperInterface
{
    public function __construct(
        private string $salt = '',
    ) {
    }

    public function isProxyCandidate(Definition $definition, ?bool &$asGhostObject = null, ?string $id = null): bool
    {
        $asGhostObject = false;

        if ($definition->hasTag('proxy')) {
            if (!$definition->isLazy()) {
                throw new InvalidArgumentException(\sprintf('Invalid definition for service "%s": setting the "proxy" tag on a service requires it to be "lazy".', $id ?? $definition->getClass()));
            }

            return true;
        }

        if (!$definition->isLazy()) {
            return false;
        }

        if (!($class = $definition->getClass()) || !(class_exists($class) || interface_exists($class, false))) {
            return false;
        }

        if ($definition->getFactory()) {
            return true;
        }

        foreach ($definition->getMethodCalls() as $call) {
            if ($call[2] ?? false) {
                return true;
            }
        }

        if (\PHP_VERSION_ID < 80400) {
            try {
                $asGhostObject = (bool) ProxyHelper::generateLazyGhost(new \ReflectionClass($class));
            } catch (LogicException) {
            }

            return true;
        }

        try {
            $asGhostObject = (bool) (new \ReflectionClass($class))->newLazyGhost(static fn () => null);
        } catch (\Error $e) {
            if (__FILE__ !== $e->getFile()) {
                throw $e;
            }
        }

        return true;
    }

    public function getProxyFactoryCode(Definition $definition, string $id, string $factoryCode): string
    {
        $instantiation = 'return';

        if ($definition->isShared()) {
            $instantiation .= \sprintf(' $container->%s[%s] =', $definition->isPublic() && !$definition->isPrivate() ? 'services' : 'privates', var_export($id, true));
        }

        $asGhostObject = str_contains($factoryCode, '$proxy');
        $proxyClass = $this->getProxyClass($definition, $asGhostObject);

        if (!$asGhostObject) {
            if ($definition->getClass() === $proxyClass) {
                return <<<EOF
                        if (true === \$lazyLoad) {
                            $instantiation new \ReflectionClass('$proxyClass')->newLazyProxy(static fn () => $factoryCode);
                        }


                EOF;
            }

            return <<<EOF
                    if (true === \$lazyLoad) {
                        $instantiation \$container->createProxy('$proxyClass', static fn () => \\$proxyClass::createLazyProxy(static fn () => $factoryCode));
                    }


            EOF;
        }

        if (\PHP_VERSION_ID < 80400) {
            $factoryCode = \sprintf('static fn ($proxy) => %s', $factoryCode);

            return <<<EOF
                    if (true === \$lazyLoad) {
                        $instantiation \$container->createProxy('$proxyClass', static fn () => \\$proxyClass::createLazyGhost($factoryCode));
                    }


            EOF;
        }

        $factoryCode = \sprintf('static function ($proxy) use ($container) { %s; }', $factoryCode);

        return <<<EOF
                if (true === \$lazyLoad) {
                    $instantiation new \ReflectionClass('$proxyClass')->newLazyGhost($factoryCode);
                }


        EOF;
    }

    public function getProxyCode(Definition $definition, ?string $id = null): string
    {
        if (!$this->isProxyCandidate($definition, $asGhostObject, $id)) {
            throw new InvalidArgumentException(\sprintf('Cannot instantiate lazy proxy for service "%s".', $id ?? $definition->getClass()));
        }
        $proxyClass = $this->getProxyClass($definition, $asGhostObject, $class);

        if ($asGhostObject) {
            if (\PHP_VERSION_ID >= 80400) {
                return '';
            }

            try {
                return ($class?->isReadOnly() ? 'readonly ' : '').'class '.$proxyClass.ProxyHelper::generateLazyGhost($class);
            } catch (LogicException $e) {
                throw new InvalidArgumentException(\sprintf('Cannot generate lazy ghost for service "%s".', $id ?? $definition->getClass()), 0, $e);
            }
        }

        if ($definition->getClass() === $proxyClass) {
            return '';
        }

        $interfaces = [];

        if ($definition->hasTag('proxy')) {
            foreach ($definition->getTag('proxy') as $tag) {
                if (!isset($tag['interface'])) {
                    throw new InvalidArgumentException(\sprintf('Invalid definition for service "%s": the "interface" attribute is missing on a "proxy" tag.', $id ?? $definition->getClass()));
                }
                if (!interface_exists($tag['interface']) && !class_exists($tag['interface'], false)) {
                    throw new InvalidArgumentException(\sprintf('Invalid definition for service "%s": several "proxy" tags found but "%s" is not an interface.', $id ?? $definition->getClass(), $tag['interface']));
                }
                if ('object' !== $definition->getClass() && !is_a($class->name, $tag['interface'], true)) {
                    throw new InvalidArgumentException(\sprintf('Invalid "proxy" tag for service "%s": class "%s" doesn\'t implement "%s".', $id ?? $definition->getClass(), $definition->getClass(), $tag['interface']));
                }
                $interfaces[] = new \ReflectionClass($tag['interface']);
            }

            $class = 1 === \count($interfaces) && !$interfaces[0]->isInterface() ? array_pop($interfaces) : null;
        } elseif ($class->isInterface()) {
            $interfaces = [$class];
            $class = null;
        }

        try {
            return ($class?->isReadOnly() ? 'readonly ' : '').'class '.$proxyClass.ProxyHelper::generateLazyProxy($class, $interfaces);
        } catch (LogicException $e) {
            throw new InvalidArgumentException(\sprintf('Cannot generate lazy proxy for service "%s".', $id ?? $definition->getClass()), 0, $e);
        }
    }

    public function getProxyClass(Definition $definition, bool $asGhostObject, ?\ReflectionClass &$class = null): string
    {
        $class = 'object' !== $definition->getClass() ? $definition->getClass() : 'stdClass';
        $class = new \ReflectionClass($class);

        if (\PHP_VERSION_ID < 80400) {
            return preg_replace('/^.*\\\\/', '', $definition->getClass())
                .($asGhostObject ? 'Ghost' : 'Proxy')
                .ucfirst(substr(hash('xxh128', $this->salt.'+'.$class->name.'+'.serialize($definition->getTag('proxy'))), -7));
        }

        if ($asGhostObject) {
            return $class->name;
        }

        if (!$definition->hasTag('proxy') && !$class->isInterface()) {
            $parent = $class;
            do {
                $extendsInternalClass = $parent->isInternal();
            } while (!$extendsInternalClass && $parent = $parent->getParentClass());

            if (!$extendsInternalClass) {
                return $class->name;
            }
        }

        return preg_replace('/^.*\\\\/', '', $definition->getClass()).'Proxy'
            .ucfirst(substr(hash('xxh128', $this->salt.'+'.$class->name.'+'.serialize($definition->getTag('proxy'))), -7));
    }
}
