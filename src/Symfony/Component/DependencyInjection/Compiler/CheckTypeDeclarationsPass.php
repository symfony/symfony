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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\InvalidParameterTypeException;
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
        if (!$value instanceof Definition) {
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
            $reflectionMethod = $this->getReflectionMethod($value, $methodCall[0]);

            $this->checkTypeDeclarations($value, $reflectionMethod, $methodCall[1]);
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * @throws InvalidArgumentException When not enough parameters are defined for the method
     */
    private function checkTypeDeclarations(Definition $checkedDefinition, \ReflectionFunctionAbstract $reflectionFunction, array $configurationArguments): void
    {
        $numberOfRequiredParameters = $reflectionFunction->getNumberOfRequiredParameters();

        if (\count($configurationArguments) < $numberOfRequiredParameters) {
            throw new InvalidArgumentException(sprintf('Invalid definition for service "%s": "%s::%s()" requires %d arguments, %d passed.', $this->currentId, $reflectionFunction->class, $reflectionFunction->name, $numberOfRequiredParameters, \count($configurationArguments)));
        }

        $reflectionParameters = $reflectionFunction->getParameters();
        $checksCount = min($reflectionFunction->getNumberOfParameters(), \count($configurationArguments));

        for ($i = 0; $i < $checksCount; ++$i) {
            if (!$reflectionParameters[$i]->hasType() || $reflectionParameters[$i]->isVariadic()) {
                continue;
            }

            $this->checkType($checkedDefinition, $configurationArguments[$i], $reflectionParameters[$i]);
        }

        if ($reflectionFunction->isVariadic() && ($lastParameter = end($reflectionParameters))->hasType()) {
            $variadicParameters = \array_slice($configurationArguments, $lastParameter->getPosition());

            foreach ($variadicParameters as $variadicParameter) {
                $this->checkType($checkedDefinition, $variadicParameter, $lastParameter);
            }
        }
    }

    /**
     * @throws InvalidParameterTypeException When a parameter is not compatible with the declared type
     */
    private function checkType(Definition $checkedDefinition, $configurationArgument, \ReflectionParameter $parameter): void
    {
        $parameterTypeName = $parameter->getType()->getName();

        $referencedDefinition = $configurationArgument;

        if ($referencedDefinition instanceof Reference) {
            if (!$this->container->has($referencedDefinition)) {
                return;
            }

            $referencedDefinition = $this->container->findDefinition((string) $referencedDefinition);
        }

        if ('self' === $parameterTypeName) {
            $parameterTypeName = $parameter->getDeclaringClass()->getName();
        }
        if ('static' === $parameterTypeName) {
            $parameterTypeName = $checkedDefinition->getClass();
        }

        if ($referencedDefinition instanceof Definition) {
            $class = $referencedDefinition->getClass();

            if (!$class || (!$this->autoload && !class_exists($class, false) && !interface_exists($class, false))) {
                return;
            }

            if (!is_a($class, $parameterTypeName, true)) {
                throw new InvalidParameterTypeException($this->currentId, $class, $parameter);
            }
        } else {
            if (null === $configurationArgument && $parameter->allowsNull()) {
                return;
            }

            if (\in_array($parameterTypeName, self::SCALAR_TYPES, true) && is_scalar($configurationArgument)) {
                return;
            }

            if ('iterable' === $parameterTypeName && $configurationArgument instanceof IteratorArgument) {
                return;
            }

            if ('Traversable' === $parameterTypeName && $configurationArgument instanceof IteratorArgument) {
                return;
            }

            if ($configurationArgument instanceof Parameter) {
                return;
            }

            $checkFunction = sprintf('is_%s', $parameter->getType()->getName());

            if (!$parameter->getType()->isBuiltin() || !$checkFunction($configurationArgument)) {
                throw new InvalidParameterTypeException($this->currentId, \gettype($configurationArgument), $parameter);
            }
        }
    }
}
