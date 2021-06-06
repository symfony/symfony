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
use ProxyManager\ProxyGenerator\LazyLoadingValueHolderGenerator as BaseGenerator;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
class LazyLoadingValueHolderGenerator extends BaseGenerator
{
    /**
     * {@inheritdoc}
     */
    public function generate(\ReflectionClass $originalClass, ClassGenerator $classGenerator, array $proxyOptions = []): void
    {
        parent::generate($originalClass, $classGenerator, $proxyOptions);

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

    public function getProxifiedClass(Definition $definition): ?string
    {
        if (!$definition->hasTag('proxy')) {
            return ($class = $definition->getClass()) && (class_exists($class) || interface_exists($class, false)) ? $class : null;
        }
        if (!$definition->isLazy()) {
            throw new \InvalidArgumentException(sprintf('Invalid definition for service of class "%s": setting the "proxy" tag on a service requires it to be "lazy".', $definition->getClass()));
        }
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
