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
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\VarExporter\ProxyHelper;

/**
 * @internal
 */
final class CheckCallableAdapterPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if (!$value instanceof Definition || $value->hasErrors() || $value->isAbstract() || ['Closure', 'fromCallable'] !== $value->getFactory() || !($interface = $value->getClass()) || 'Closure' === $interface) {
            return parent::processValue($value, $isRoot);
        }

        $id = $this->currentId;
        while (preg_match('/^\.autowire_inline\.(.+)\.\d+$/D', $id, $matches)) {
            $id = $matches[1];
        }
        $r = $this->container->getReflectionClass($interface, false);
        if (!$r?->isInterface()) {
            return parent::processValue($value, $isRoot);
        }
        if (1 !== \count($methods = $r->getMethods())) {
            throw new RuntimeException(\sprintf('Cannot create adapter for service "%s" because interface "%s" doesn\'t have exactly one method.', $id, $interface));
        }
        $interface = $methods[0];
        if ($interface->isStatic()) {
            throw new RuntimeException(\sprintf('Cannot create adapter for service "%s" because interface method "%s::%s()" is static.', $id, $interface->class, $interface->name));
        }
        $callable = $value->getArguments()[0] ?? null;
        if (\is_string($callable) && str_contains($callable, '::')) {
            $callable = explode('::', $callable, 2);
        }
        if ($callable instanceof Reference || $callable instanceof Definition) {
            $callable = [$callable, '__invoke'];
        }
        if (!\is_array($callable) || !isset($callable[0], $callable[1])) {
            return parent::processValue($value, $isRoot);
        }
        [$target, $method] = $callable;
        if ($target instanceof Reference) {
            if (!$this->container->hasDefinition($target)) {
                return parent::processValue($value, $isRoot);
            }
            $target = $this->container->findDefinition($target);
        }
        $definition = $target instanceof Definition ? $target : null;
        if ($definition && ($definition->getFactory() || $definition->isSynthetic() || \in_array(true, array_column($definition->getMethodCalls(), 2), true))) {
            return parent::processValue($value, $isRoot);
        }
        $class = $definition ? $definition->getClass() : $target;
        if (!\is_string($class) || !$r = $this->container->getReflectionClass($class, false)) {
            return parent::processValue($value, $isRoot);
        }
        if ((!$r->hasMethod($method) || !$r->getMethod($method)->isPublic()) && $r->hasMethod('__call')) {
            return parent::processValue($value, $isRoot);
        }

        $target = $r->hasMethod($method) ? $r->getMethod($method) : null;
        $minimumArguments = $interface->getNumberOfRequiredParameters();
        foreach ($interface->getParameters() as $i => $parameter) {
            if ($parameter->isPassedByReference()) {
                $minimumArguments = $interface->isVariadic() ? $interface->getNumberOfParameters() - 1 : max($minimumArguments, 1 + $i);
            }
        }
        $reason = null;
        if (!$target) {
            $reason = 'the target method does not exist.';
        } elseif (!$target->isPublic()) {
            $reason = 'the target method must be public.';
        } elseif ($target->getNumberOfRequiredParameters() > $minimumArguments) {
            $reason = \sprintf('the target needs more arguments than the adapter guarantees (%d required, %d guaranteed).', $target->getNumberOfRequiredParameters(), $minimumArguments);
        } elseif ($target->isInternal() && !$target->isVariadic() && $interface->getNumberOfParameters() > $target->getNumberOfParameters()) {
            $reason = \sprintf('the interface declares more parameters than the target accepts (%d declared, %d accepted).', $interface->getNumberOfParameters(), $target->getNumberOfParameters());
        } else {
            $parameters = $target->getParameters();
            foreach ($interface->getParameters() as $i => $parameter) {
                $targetParameter = $parameters[$i] ?? ($target->isVariadic() ? end($parameters) : null);
                if ($targetParameter && !$this->typesOverlap($parameter->getType(), $interface, $targetParameter->getType(), $target)) {
                    $reason = \sprintf('parameter #%d has incompatible types.', 1 + $i);
                    break;
                }
            }
            if (!$reason && 'void' !== (string) $interface->getReturnType() && 'never' !== (string) $target->getReturnType() && !$this->typesOverlap($target->getReturnType(), $target, $interface->getReturnType(), $interface)) {
                $reason = 'the return types are incompatible.';
            }
        }

        if ($reason) {
            $signature = static fn (\ReflectionMethod $method) => $method->class.'::'.substr($signature = ProxyHelper::exportSignature($method), strpos($signature, 'function ') + 9);

            throw new RuntimeException(\sprintf('Cannot create adapter for service "%s": "%s" is incompatible with "%s": ', $id, $target ? $signature($target) : $class.'::'.$method.'()', $signature($interface)).$reason);
        }

        return parent::processValue($value, $isRoot);
    }

    private function typesOverlap(?\ReflectionType $a, \ReflectionMethod $aMethod, ?\ReflectionType $b, \ReflectionMethod $bMethod): bool
    {
        if (!$a || !$b || ($a->allowsNull() || 'void' === (string) $a) && ($b->allowsNull() || 'void' === (string) $b)) {
            return true;
        }
        if ($a instanceof \ReflectionUnionType || $a instanceof \ReflectionIntersectionType) {
            $intersection = $a instanceof \ReflectionIntersectionType;
            foreach ($a->getTypes() as $type) {
                if ($intersection !== $this->typesOverlap($type, $aMethod, $b, $bMethod)) {
                    return !$intersection;
                }
            }

            return $intersection;
        }
        if ($b instanceof \ReflectionUnionType || $b instanceof \ReflectionIntersectionType) {
            $intersection = $b instanceof \ReflectionIntersectionType;
            foreach ($b->getTypes() as $type) {
                if ($intersection !== $this->typesOverlap($a, $aMethod, $type, $bMethod)) {
                    return !$intersection;
                }
            }

            return $intersection;
        }
        if (!$a instanceof \ReflectionNamedType || !$b instanceof \ReflectionNamedType) {
            return true;
        }
        $a = $this->resolveType($a, $aMethod);
        $b = $this->resolveType($b, $bMethod);
        if ($a === $b || 'mixed' === $a || 'mixed' === $b) {
            return true;
        }
        if ('null' === $a || 'null' === $b) {
            return false;
        }
        if (\in_array($a, ['bool', 'true', 'false', 'int', 'float', 'string'], true) && \in_array($b, ['bool', 'true', 'false', 'int', 'float', 'string'], true)) {
            return !\in_array($b, ['true', 'false'], true) || 'bool' === $a;
        }
        $aClass = $this->container->getReflectionClass($a, false);
        $bClass = $this->container->getReflectionClass($b, false);
        if (!$aClass && !\in_array($a, ['array', 'bool', 'true', 'false', 'int', 'float', 'string', 'object', 'iterable', 'callable', 'never'], true) || !$bClass && !\in_array($b, ['array', 'bool', 'true', 'false', 'int', 'float', 'string', 'object', 'iterable', 'callable', 'never'], true)) {
            return true;
        }
        if ($aClass && $bClass) {
            return is_a($a, $b, true) || is_a($b, $a, true) || $aClass->isInterface() && !$bClass->isFinal() || $bClass->isInterface() && !$aClass->isFinal();
        }
        if ($bClass) {
            return 'object' === $a
                || 'callable' === $a && ($bClass->hasMethod('__invoke') || !$bClass->isFinal())
                || 'iterable' === $a && (is_a($b, \Traversable::class, true) || $bClass->isInterface() || !$bClass->isFinal());
        }
        if ($aClass) {
            return 'object' === $b
                || 'string' === $b && (is_a($a, \Stringable::class, true) || !$aClass->isFinal())
                || 'callable' === $b && ($aClass->hasMethod('__invoke') || !$aClass->isFinal())
                || 'iterable' === $b && (is_a($a, \Traversable::class, true) || $aClass->isInterface() || !$aClass->isFinal());
        }

        return 'object' === $a && 'string' === $b
            || 'iterable' === $a && \in_array($b, ['array', 'object', 'string', 'callable'], true)
            || 'iterable' === $b && \in_array($a, ['array', 'object', 'callable'], true)
            || 'callable' === $a && \in_array($b, ['array', 'object', 'string'], true)
            || 'callable' === $b && \in_array($a, ['array', 'object', 'string'], true);
    }

    private function resolveType(\ReflectionNamedType $type, \ReflectionMethod $method): string
    {
        return match ($type->getName()) {
            'self', 'static' => $method->class,
            'parent' => $method->getDeclaringClass()->getParentClass()->name,
            'void' => 'null',
            default => $type->getName(),
        };
    }
}
