<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\Internal;

use Laminas\Code\Generator\ClassGenerator;
use ProxyManager\ProxyGenerator\LazyLoadingGhostGenerator;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolderGenerator;
use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
class ProxyGenerator implements ProxyGeneratorInterface
{
    private readonly ProxyGeneratorInterface $generator;

    public function asGhostObject(bool $asGhostObject): static
    {
        $clone = clone $this;
        $clone->generator = $asGhostObject ? new LazyLoadingGhostGenerator() : new LazyLoadingValueHolderGenerator();

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(\ReflectionClass $originalClass, ClassGenerator $classGenerator, array $proxyOptions = []): void
    {
        $this->generator->generate($originalClass, $classGenerator, $proxyOptions);

        foreach ($classGenerator->getMethods() as $method) {
            if (str_starts_with($originalClass->getFilename(), __FILE__)) {
                $method->setBody(str_replace(var_export($originalClass->name, true), '__CLASS__', $method->getBody()));
            }
        }

        if (str_starts_with($originalClass->getFilename(), __FILE__)) {
            $interfaces = $classGenerator->getImplementedInterfaces();
            array_pop($interfaces);
            $classGenerator->setImplementedInterfaces(array_merge($interfaces, $originalClass->getInterfaceNames()));
        }
    }

    public function getProxifiedClass(Definition $definition, bool &$asGhostObject = null): ?string
    {
        if (!$definition->hasTag('proxy')) {
            if (!($class = $definition->getClass()) || !(class_exists($class) || interface_exists($class, false))) {
                return null;
            }

            $class = new \ReflectionClass($class);
            $name = $class->name;

            if ($asGhostObject = !$class->isAbstract() && !$class->isInterface() && ('stdClass' === $class->name || !$class->isInternal())) {
                while ($class = $class->getParentClass()) {
                    if (!$asGhostObject = 'stdClass' === $class->name || !$class->isInternal()) {
                        break;
                    }
                }
            }

            return $name;
        }
        if (!$definition->isLazy()) {
            throw new \InvalidArgumentException(sprintf('Invalid definition for service of class "%s": setting the "proxy" tag on a service requires it to be "lazy".', $definition->getClass()));
        }
        $asGhostObject = false;
        $tags = $definition->getTag('proxy');
        if (!isset($tags[0]['interface'])) {
            throw new \InvalidArgumentException(sprintf('Invalid definition for service of class "%s": the "interface" attribute is missing on the "proxy" tag.', $definition->getClass()));
        }
        if (1 === \count($tags)) {
            return class_exists($tags[0]['interface']) || interface_exists($tags[0]['interface'], false) ? $tags[0]['interface'] : null;
        }

        $proxyInterface = 'LazyProxy';
        $interfaces = '';
        foreach ($tags as $tag) {
            if (!isset($tag['interface'])) {
                throw new \InvalidArgumentException(sprintf('Invalid definition for service of class "%s": the "interface" attribute is missing on a "proxy" tag.', $definition->getClass()));
            }
            if (!interface_exists($tag['interface'])) {
                throw new \InvalidArgumentException(sprintf('Invalid definition for service of class "%s": several "proxy" tags found but "%s" is not an interface.', $definition->getClass(), $tag['interface']));
            }

            $proxyInterface .= '\\'.$tag['interface'];
            $interfaces .= ', \\'.$tag['interface'];
        }

        if (!interface_exists($proxyInterface)) {
            $i = strrpos($proxyInterface, '\\');
            $namespace = substr($proxyInterface, 0, $i);
            $interface = substr($proxyInterface, 1 + $i);
            $interfaces = substr($interfaces, 2);

            eval("namespace {$namespace}; interface {$interface} extends {$interfaces} {}");
        }

        return $proxyInterface;
    }
}
