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
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Looks for definitions with autowiring enabled and registers their corresponding "#[Required]" methods as setters.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AutowireRequiredMethodsPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    protected function processValue(mixed $value, bool $isRoot = false): mixed
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
        $setters = [];

        foreach ($value->getMethodCalls() as [$method]) {
            $alreadyCalledMethods[strtolower($method)] = true;
        }

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $r = $reflectionMethod;

            if ($r->isConstructor() || isset($alreadyCalledMethods[strtolower($r->name)])) {
                continue;
            }

            while (true) {
                if ($attributes = $r->getAttributes(Required::class)) {
                    $priority = $attributes[0]->newInstance()->priority ?? 0;

                    if ($this->isWither($r, $r->getDocComment() ?: '')) {
                        $withers[] = [$r->name, [], $priority, true];
                    } else {
                        $setters[] = [$r->name, [], $priority];
                    }
                    break;
                }
                if (!$r->hasPrototype()) {
                    break;
                }
                $r = $r->getPrototype();
            }
        }

        usort($setters, static fn ($a, $b) => $b[2] <=> $a[2]);
        foreach ($setters as $call) {
            $value->addMethodCall($call[0], $call[1]);
        }

        if ($withers) {
            usort($withers, static fn ($a, $b) => $b[2] <=> $a[2]);
            // Prepend withers to prevent creating circular loops
            $calls = $value->getMethodCalls();
            $value->setMethodCalls([]);
            foreach ($withers as $call) {
                $value->addMethodCall($call[0], $call[1], $call[3]);
            }
            foreach ($calls as $call) {
                $value->addMethodCall($call[0], $call[1], $call[2] ?? false);
            }
        }

        return $value;
    }

    private function isWither(\ReflectionMethod $reflectionMethod, string $doc): bool
    {
        $match = preg_match('#(?:^/\*\*|\n\s*+\*)\s*+@return\s++(static|\$this)[\s\*]#i', $doc, $matches);
        if ($match && 'static' === $matches[1]) {
            return true;
        }

        if ($match && '$this' === $matches[1]) {
            return false;
        }

        $reflectionType = $reflectionMethod->hasReturnType() ? $reflectionMethod->getReturnType() : null;

        return $reflectionType instanceof \ReflectionNamedType && 'static' === $reflectionType->getName();
    }
}
