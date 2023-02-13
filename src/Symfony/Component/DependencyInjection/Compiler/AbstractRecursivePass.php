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

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ExpressionLanguage;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractRecursivePass implements CompilerPassInterface
{
    /**
     * @var ContainerBuilder
     */
    protected $container;
    protected $currentId;

    private bool $processExpressions = false;
    private ExpressionLanguage $expressionLanguage;
    private bool $inExpression = false;

    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;

        try {
            $this->processValue($container->getDefinitions(), true);
        } finally {
            $this->container = null;
        }
    }

    /**
     * @return void
     */
    protected function enableExpressionProcessing()
    {
        $this->processExpressions = true;
    }

    protected function inExpression(bool $reset = true): bool
    {
        $inExpression = $this->inExpression;
        if ($reset) {
            $this->inExpression = false;
        }

        return $inExpression;
    }

    /**
     * Processes a value found in a definition tree.
     *
     * @return mixed
     */
    protected function processValue(mixed $value, bool $isRoot = false)
    {
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                if ($isRoot) {
                    if ($v->hasTag('container.excluded')) {
                        continue;
                    }
                    $this->currentId = $k;
                }
                if ($v !== $processedValue = $this->processValue($v, $isRoot)) {
                    $value[$k] = $processedValue;
                }
            }
        } elseif ($value instanceof ArgumentInterface) {
            $value->setValues($this->processValue($value->getValues()));
        } elseif ($value instanceof Expression && $this->processExpressions) {
            $this->getExpressionLanguage()->compile((string) $value, ['this' => 'container', 'args' => 'args']);
        } elseif ($value instanceof Definition) {
            $value->setArguments($this->processValue($value->getArguments()));
            $value->setProperties($this->processValue($value->getProperties()));
            $value->setMethodCalls($this->processValue($value->getMethodCalls()));

            $changes = $value->getChanges();
            if (isset($changes['factory'])) {
                if (\is_string($factory = $value->getFactory()) && str_starts_with($factory, '@=')) {
                    if (!class_exists(Expression::class)) {
                        throw new LogicException('Expressions cannot be used in service factories without the ExpressionLanguage component. Try running "composer require symfony/expression-language".');
                    }
                    $factory = new Expression(substr($factory, 2));
                }
                if (($factory = $this->processValue($factory)) instanceof Expression) {
                    $factory = '@='.$factory;
                }
                $value->setFactory($factory);
            }
            if (isset($changes['configurator'])) {
                $value->setConfigurator($this->processValue($value->getConfigurator()));
            }
        }

        return $value;
    }

    /**
     * @throws RuntimeException
     */
    protected function getConstructor(Definition $definition, bool $required): ?\ReflectionFunctionAbstract
    {
        if ($definition->isSynthetic()) {
            return null;
        }

        if (\is_string($factory = $definition->getFactory())) {
            if (str_starts_with($factory, '@=')) {
                return new \ReflectionFunction(static function (...$args) {});
            }

            if (!\function_exists($factory)) {
                throw new RuntimeException(sprintf('Invalid service "%s": function "%s" does not exist.', $this->currentId, $factory));
            }
            $r = new \ReflectionFunction($factory);
            if (false !== $r->getFileName() && file_exists($r->getFileName())) {
                $this->container->fileExists($r->getFileName());
            }

            return $r;
        }

        if ($factory) {
            [$class, $method] = $factory;

            if ('__construct' === $method) {
                throw new RuntimeException(sprintf('Invalid service "%s": "__construct()" cannot be used as a factory method.', $this->currentId));
            }

            if ($class instanceof Reference) {
                $factoryDefinition = $this->container->findDefinition((string) $class);
                while ((null === $class = $factoryDefinition->getClass()) && $factoryDefinition instanceof ChildDefinition) {
                    $factoryDefinition = $this->container->findDefinition($factoryDefinition->getParent());
                }
            } elseif ($class instanceof Definition) {
                $class = $class->getClass();
            } else {
                $class ??= $definition->getClass();
            }

            return $this->getReflectionMethod(new Definition($class), $method);
        }

        while ((null === $class = $definition->getClass()) && $definition instanceof ChildDefinition) {
            $definition = $this->container->findDefinition($definition->getParent());
        }

        try {
            if (!$r = $this->container->getReflectionClass($class)) {
                if (null === $class) {
                    throw new RuntimeException(sprintf('Invalid service "%s": the class is not set.', $this->currentId));
                }

                throw new RuntimeException(sprintf('Invalid service "%s": class "%s" does not exist.', $this->currentId, $class));
            }
        } catch (\ReflectionException $e) {
            throw new RuntimeException(sprintf('Invalid service "%s": ', $this->currentId).lcfirst($e->getMessage()));
        }
        if (!$r = $r->getConstructor()) {
            if ($required) {
                throw new RuntimeException(sprintf('Invalid service "%s": class%s has no constructor.', $this->currentId, sprintf($class !== $this->currentId ? ' "%s"' : '', $class)));
            }
        } elseif (!$r->isPublic()) {
            throw new RuntimeException(sprintf('Invalid service "%s": ', $this->currentId).sprintf($class !== $this->currentId ? 'constructor of class "%s"' : 'its constructor', $class).' must be public.');
        }

        return $r;
    }

    /**
     * @throws RuntimeException
     */
    protected function getReflectionMethod(Definition $definition, string $method): \ReflectionFunctionAbstract
    {
        if ('__construct' === $method) {
            return $this->getConstructor($definition, true);
        }

        while ((null === $class = $definition->getClass()) && $definition instanceof ChildDefinition) {
            $definition = $this->container->findDefinition($definition->getParent());
        }

        if (null === $class) {
            throw new RuntimeException(sprintf('Invalid service "%s": the class is not set.', $this->currentId));
        }

        if (!$r = $this->container->getReflectionClass($class)) {
            throw new RuntimeException(sprintf('Invalid service "%s": class "%s" does not exist.', $this->currentId, $class));
        }

        if (!$r->hasMethod($method)) {
            if ($r->hasMethod('__call') && ($r = $r->getMethod('__call')) && $r->isPublic()) {
                return new \ReflectionMethod(static function (...$arguments) {}, '__invoke');
            }

            throw new RuntimeException(sprintf('Invalid service "%s": method "%s()" does not exist.', $this->currentId, $class !== $this->currentId ? $class.'::'.$method : $method));
        }

        $r = $r->getMethod($method);
        if (!$r->isPublic()) {
            throw new RuntimeException(sprintf('Invalid service "%s": method "%s()" must be public.', $this->currentId, $class !== $this->currentId ? $class.'::'.$method : $method));
        }

        return $r;
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        if (!isset($this->expressionLanguage)) {
            if (!class_exists(ExpressionLanguage::class)) {
                throw new LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
            }

            $providers = $this->container->getExpressionLanguageProviders();
            $this->expressionLanguage = new ExpressionLanguage(null, $providers, function (string $arg): string {
                if ('""' === substr_replace($arg, '', 1, -1)) {
                    $id = stripcslashes(substr($arg, 1, -1));
                    $this->inExpression = true;
                    $arg = $this->processValue(new Reference($id));
                    $this->inExpression = false;
                    if (!$arg instanceof Reference) {
                        throw new RuntimeException(sprintf('"%s::processValue()" must return a Reference when processing an expression, "%s" returned for service("%s").', static::class, get_debug_type($arg), $id));
                    }
                    $arg = sprintf('"%s"', $arg);
                }

                return sprintf('$this->get(%s)', $arg);
            });
        }

        return $this->expressionLanguage;
    }
}
