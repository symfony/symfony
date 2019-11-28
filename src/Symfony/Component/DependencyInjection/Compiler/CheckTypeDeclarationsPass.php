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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\InvalidParameterTypeException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Checks whether injected parameters are compatible with type declarations.
 *
 * This pass should be run after all optimization passes.
 *
 * It can be added either:
 *  * before removing passes to check all services even if they are not currently used,
 *  * after removing passes to check only services are used in the app.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Julien Maulny <jmaulny@darkmira.fr>
 */
final class CheckTypeDeclarationsPass extends AbstractRecursivePass
{
    private const SCALAR_TYPES = ['int', 'float', 'bool', 'string'];

    private $autoload;

    /**
     * @param bool $autoload Whether services who's class in not loaded should be checked or not.
     *                       Defaults to false to save loading code during compilation.
     */
    public function __construct(bool $autoload = false)
    {
        $this->autoload = $autoload;
    }

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        if (!$value instanceof Definition || $value->hasErrors()) {
            return parent::processValue($value, $isRoot);
        }

        if (!$this->autoload && !class_exists($class = $value->getClass(), false) && !interface_exists($class, false)) {
            return parent::processValue($value, $isRoot);
        }

        if (ServiceLocator::class === $value->getClass()) {
            return parent::processValue($value, $isRoot);
        }

        if ($constructor = $this->getConstructor($value, false)) {
            $this->checkTypeDeclarations($value, $constructor, $value->getArguments());
        }

        foreach ($value->getMethodCalls() as $methodCall) {
            try {
                $reflectionMethod = $this->getReflectionMethod($value, $methodCall[0]);
            } catch (RuntimeException $e) {
                if ($value->getFactory()) {
                    continue;
                }

                throw $e;
            }

            $this->checkTypeDeclarations($value, $reflectionMethod, $methodCall[1]);
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * @throws InvalidArgumentException When not enough parameters are defined for the method
     */
    private function checkTypeDeclarations(Definition $checkedDefinition, \ReflectionFunctionAbstract $reflectionFunction, array $values): void
    {
        $numberOfRequiredParameters = $reflectionFunction->getNumberOfRequiredParameters();

        if (\count($values) < $numberOfRequiredParameters) {
            throw new InvalidArgumentException(sprintf('Invalid definition for service "%s": "%s::%s()" requires %d arguments, %d passed.', $this->currentId, $reflectionFunction->class, $reflectionFunction->name, $numberOfRequiredParameters, \count($values)));
        }

        $reflectionParameters = $reflectionFunction->getParameters();
        $checksCount = min($reflectionFunction->getNumberOfParameters(), \count($values));

        for ($i = 0; $i < $checksCount; ++$i) {
            if (!$reflectionParameters[$i]->hasType() || $reflectionParameters[$i]->isVariadic()) {
                continue;
            }

            $this->checkType($checkedDefinition, $values[$i], $reflectionParameters[$i]);
        }

        if ($reflectionFunction->isVariadic() && ($lastParameter = end($reflectionParameters))->hasType()) {
            $variadicParameters = \array_slice($values, $lastParameter->getPosition());

            foreach ($variadicParameters as $variadicParameter) {
                $this->checkType($checkedDefinition, $variadicParameter, $lastParameter);
            }
        }
    }

    /**
     * @throws InvalidParameterTypeException When a parameter is not compatible with the declared type
     */
    private function checkType(Definition $checkedDefinition, $value, \ReflectionParameter $parameter): void
    {
        $type = $parameter->getType()->getName();

        if ($value instanceof Reference) {
            if (!$this->container->has($value = (string) $value)) {
                return;
            }

            if ('service_container' === $value && is_a($type, Container::class, true)) {
                return;
            }

            $value = $this->container->findDefinition($value);
        }

        if ('self' === $type) {
            $type = $parameter->getDeclaringClass()->getName();
        }

        if ('static' === $type) {
            $type = $checkedDefinition->getClass();
        }

        if ($value instanceof Definition) {
            $class = $value->getClass();

            if (!$class || (!$this->autoload && !class_exists($class, false) && !interface_exists($class, false))) {
                return;
            }

            if ('callable' === $type && method_exists($class, '__invoke')) {
                return;
            }

            if ('iterable' === $type && is_subclass_of($class, 'Traversable')) {
                return;
            }

            if ('object' === $type) {
                return;
            }

            if (is_a($class, $type, true)) {
                return;
            }

            throw new InvalidParameterTypeException($this->currentId, $class, $parameter);
        }

        if ($value instanceof Parameter) {
            $value = $this->container->getParameter($value);
        } elseif (\is_string($value) && '%' === ($value[0] ?? '') && preg_match('/^%([^%]+)%$/', $value, $match)) {
            $value = $this->container->getParameter($match[1]);
        }

        if (null === $value && $parameter->allowsNull()) {
            return;
        }

        if (\in_array($type, self::SCALAR_TYPES, true) && is_scalar($value)) {
            return;
        }

        if ('callable' === $type && \is_array($value) && isset($value[0]) && ($value[0] instanceof Reference || $value[0] instanceof Definition)) {
            return;
        }

        if ('iterable' === $type && (\is_array($value) || $value instanceof \Traversable || $value instanceof IteratorArgument)) {
            return;
        }

        if ('Traversable' === $type && ($value instanceof \Traversable || $value instanceof IteratorArgument)) {
            return;
        }

        $checkFunction = sprintf('is_%s', $parameter->getType()->getName());

        if (!$parameter->getType()->isBuiltin() || !$checkFunction($value)) {
            throw new InvalidParameterTypeException($this->currentId, \is_object($value) ? \get_class($value) : \gettype($value), $parameter);
        }
    }
}
