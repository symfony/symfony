<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Applies the "container.service_factory" tag by scraping class methods used for Definition instances.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class ServiceFactoryTagPass extends AbstractRecursivePass
{
    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        if (!$value instanceof Definition || !$value->hasTag('container.service_factory')) {
            return parent::processValue($value, $isRoot);
        }

        if (!$r = $this->container->getReflectionClass($class = $value->getClass())) {
            throw new InvalidArgumentException(sprintf('Class "%s" used for service factory "%s" cannot be found.', $class, $this->currentId));
        }

        if ($r->isAbstract()) {
            throw new InvalidArgumentException(sprintf('Class "%s" used for service factory "%s" cannot be abstract.', $class, $this->currentId));
        }

        foreach ($this->getMethodsToFactorize($r) as $id => $method) {
            $this->container->register($id)
                ->setFactory(array(new Reference($this->currentId), $method->getName()))
                ->setClass($this->getClass($method));
        }
    }

    /**
     * Gets the list of methods to factorize.
     *
     * @param \ReflectionClass $reflectionClass
     *
     * @return \ReflectionMethod[]
     */
    private function getMethodsToFactorize(\ReflectionClass $reflectionClass)
    {
        $methodsToFactorize = array();

        foreach ($reflectionClass->getMethods() as $method) {
            if ($method->isConstructor()) {
                continue;
            }

            while (true) {
                if (false !== $doc = $method->getDocComment()) {
                    if (false !== stripos($doc, '@service') && preg_match('#(?:^/\*\*|\n\s*+\*)\s*+@service(?:\s(\S+)?|\*/$)#i', $doc, $matches)) {
                        if (!$method->isPublic()) {
                            throw new InvalidArgumentException(sprintf('Method "%s::%s()" must be public in order to create a service from it.', $reflectionClass->getName(), $name));
                        }

                        $id = isset($matches[1]) ? $matches[1] : Container::underscore($method->getName());
                        if (isset($methodsToFactorize[$id]) || $this->container->has($id)) {
                            throw new InvalidArgumentException(sprintf('Cannot create service "%s" from service factory "%s" as it already exists.', $id, $this->currentId));
                        }

                        $methodsToFactorize[$id] = $method;
                        break;
                    }
                    if (false === stripos($doc, '@inheritdoc') || !preg_match('#(?:^/\*\*|\n\s*+\*)\s*+(?:\{@inheritdoc\}|@inheritdoc)(?:\s|\*/$)#i', $doc)) {
                        break;
                    }
                }
                try {
                    $method = $method->getPrototype();
                } catch (\ReflectionException $e) {
                    break; // method has no prototype
                }
            }
        }

        return $methodsToFactorize;
    }

    private function getClass(\ReflectionMethod $reflectionMethod)
    {
        if (method_exists(\ReflectionMethod::class, 'getReturnType')) {
            $returnType = $reflectionMethod->getReturnType();
            if (null !== $returnType && !$returnType->isBuiltin()) {
                $returnType = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : $returnType;
                switch (strtolower($returnType)) {
                    case 'self':
                        return $reflectionMethod->getDeclaringClass()->getName();
                    case 'parent':
                        return get_parent_class($reflectionMethod->getDeclaringClass()->getName()) ?: null;
                    default:
                        return $returnType;
                }
            }
        }

        return null;
    }
}
