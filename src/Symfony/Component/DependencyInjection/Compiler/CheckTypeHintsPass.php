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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\InvalidParameterTypeHintException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;

/**
 * Checks whether injected parameters types are compatible with type hints.
 * This pass should be run after all optimization passes.
 * So it can be added either:
 *    * before removing (PassConfig::TYPE_BEFORE_REMOVING) so that it will check
 *          all services, even if they are not currently used,
 *    * after removing (PassConfig::TYPE_AFTER_REMOVING) so that it will check
 *          only services you are using.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Julien Maulny <jmaulny@darkmira.fr>
 */
class CheckTypeHintsPass extends AbstractRecursivePass
{
    /**
     * If set to true, allows to autoload classes during compilation
     * in order to check type hints on parameters that are not yet loaded.
     * Defaults to false to prevent code loading during compilation.
     *
     * @param bool
     */
    private $autoload;

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

        if (!$this->autoload && !class_exists($className = $this->getClassName($value), false) && !interface_exists($className, false)) {
            return parent::processValue($value, $isRoot);
        }

        if (ServiceLocator::class === $value->getClass()) {
            return parent::processValue($value, $isRoot);
        }

        if (null !== $constructor = $this->getConstructor($value, false)) {
            $this->checkArgumentsTypeHints($constructor, $value->getArguments());
        }

        foreach ($value->getMethodCalls() as $methodCall) {
            $reflectionMethod = $this->getReflectionMethod($value, $methodCall[0]);

            $this->checkArgumentsTypeHints($reflectionMethod, $methodCall[1]);
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * Check type hints for every parameter of a method/constructor.
     *
     * @throws InvalidArgumentException on type hint incompatibility
     */
    private function checkArgumentsTypeHints(\ReflectionFunctionAbstract $reflectionFunction, array $configurationArguments): void
    {
        $numberOfRequiredParameters = $reflectionFunction->getNumberOfRequiredParameters();

        if (count($configurationArguments) < $numberOfRequiredParameters) {
            throw new InvalidArgumentException(sprintf(
                'Invalid definition for service "%s": "%s::%s()" requires %d arguments, %d passed.', $this->currentId, $reflectionFunction->class, $reflectionFunction->name, $numberOfRequiredParameters, count($configurationArguments)));
        }

        $reflectionParameters = $reflectionFunction->getParameters();
        $checksCount = min($reflectionFunction->getNumberOfParameters(), count($configurationArguments));

        for ($i = 0; $i < $checksCount; ++$i) {
            if (!$reflectionParameters[$i]->hasType() || $reflectionParameters[$i]->isVariadic()) {
                continue;
            }

            $this->checkTypeHint($configurationArguments[$i], $reflectionParameters[$i]);
        }

        if ($reflectionFunction->isVariadic() && ($lastParameter = end($reflectionParameters))->hasType()) {
            $variadicParameters = array_slice($configurationArguments, $lastParameter->getPosition());

            foreach ($variadicParameters as $variadicParameter) {
                $this->checkTypeHint($variadicParameter, $lastParameter);
            }
        }
    }

    /**
     * Check type hints compatibility between
     * a definition argument and a reflection parameter.
     *
     * @throws InvalidArgumentException on type hint incompatibility
     */
    private function checkTypeHint($configurationArgument, \ReflectionParameter $parameter): void
    {
        $referencedDefinition = $configurationArgument;

        if ($referencedDefinition instanceof Reference) {
            $referencedDefinition = $this->container->findDefinition((string) $referencedDefinition);
        }

        if ($referencedDefinition instanceof Definition) {
            $class = $this->getClassName($referencedDefinition);

            if (!$this->autoload && !class_exists($class, false)) {
                return;
            }

            if (!is_a($class, $parameter->getType()->getName(), true)) {
                throw new InvalidParameterTypeHintException($this->currentId, null === $class ? 'null' : $class, $parameter);
            }
        } else {
            if (null === $configurationArgument && $parameter->allowsNull()) {
                return;
            }

            if ($parameter->getType()->isBuiltin() && is_scalar($configurationArgument)) {
                return;
            }

            if ('iterable' === $parameter->getType()->getName() && $configurationArgument instanceof IteratorArgument) {
                return;
            }

            if ('Traversable' === $parameter->getType()->getName() && $configurationArgument instanceof IteratorArgument) {
                return;
            }

            $checkFunction = 'is_'.$parameter->getType()->getName();

            if (!$parameter->getType()->isBuiltin() || !$checkFunction($configurationArgument)) {
                throw new InvalidParameterTypeHintException($this->currentId, gettype($configurationArgument), $parameter);
            }
        }
    }

    /**
     * Get class name from value that can have a factory.
     *
     * @return string|null
     */
    private function getClassName($value)
    {
        if (is_array($factory = $value->getFactory())) {
            list($class, $method) = $factory;
            if ($class instanceof Reference) {
                $class = $this->container->findDefinition((string) $class)->getClass();
            } elseif (null === $class) {
                $class = $value->getClass();
            } elseif ($class instanceof Definition) {
                $class = $this->getClassName($class);
            }
        } else {
            $class = $value->getClass();
        }

        return $class;
    }
}
