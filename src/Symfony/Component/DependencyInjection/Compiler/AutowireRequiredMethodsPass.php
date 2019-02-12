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

use Symfony\Component\DependencyInjection\Definition;

/**
 * Looks for definitions with autowiring enabled and registers their corresponding "@required" methods as setters.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AutowireRequiredMethodsPass extends AbstractRecursivePass
{
    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        $value = parent::processValue($value, $isRoot);

        if (!$value instanceof Definition || !$value->isAutowired() || $value->isAbstract() || !$value->getClass()) {
            return $value;
        }
        if (!$reflectionClass = $this->container->getReflectionClass($value->getClass(), false)) {
            return $value;
        }

        $alreadyCalledMethods = [];
        $withers = [];

        foreach ($value->getMethodCalls() as list($method)) {
            $alreadyCalledMethods[strtolower($method)] = true;
        }

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $r = $reflectionMethod;

            if ($r->isConstructor() || isset($alreadyCalledMethods[strtolower($r->name)])) {
                continue;
            }

            while (true) {
                if (false !== $doc = $r->getDocComment()) {
                    if (false !== stripos($doc, '@required') && preg_match('#(?:^/\*\*|\n\s*+\*)\s*+@required(?:\s|\*/$)#i', $doc)) {
                        if (preg_match('#(?:^/\*\*|\n\s*+\*)\s*+@return\s++static[\s\*]#i', $doc)) {
                            $withers[] = [$reflectionMethod->name, [], true];
                        } else {
                            $value->addMethodCall($reflectionMethod->name, []);
                        }
                        break;
                    }
                    if (false === stripos($doc, '@inheritdoc') || !preg_match('#(?:^/\*\*|\n\s*+\*)\s*+(?:\{@inheritdoc\}|@inheritdoc)(?:\s|\*/$)#i', $doc)) {
                        break;
                    }
                }
                try {
                    $r = $r->getPrototype();
                } catch (\ReflectionException $e) {
                    break; // method has no prototype
                }
            }
        }

        if ($withers) {
            // Prepend withers to prevent creating circular loops
            $setters = $value->getMethodCalls();
            $value->setMethodCalls($withers);
            foreach ($setters as $call) {
                $value->addMethodCall($call[0], $call[1], $call[2] ?? false);
            }
        }

        return $value;
    }
}
