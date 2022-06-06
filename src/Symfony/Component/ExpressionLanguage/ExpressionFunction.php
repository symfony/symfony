<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage;

/**
 * Represents a function that can be used in an expression.
 *
 * A function is defined by two PHP callables. The callables are used
 * by the language to compile and/or evaluate the function.
 *
 * The "compiler" function is used at compilation time and must return a
 * PHP representation of the function call (it receives the function
 * arguments as arguments).
 *
 * The "evaluator" function is used for expression evaluation and must return
 * the value of the function call based on the values defined for the
 * expression (it receives the values as a first argument and the function
 * arguments as remaining arguments).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionFunction
{
    private $name;
    private $compiler;
    private $evaluator;

    /**
     * @param string   $name      The function name
     * @param callable $compiler  A callable able to compile the function
     * @param callable $evaluator A callable able to evaluate the function
     */
    public function __construct(string $name, callable $compiler, callable $evaluator)
    {
        $this->name = $name;
        $this->compiler = $compiler instanceof \Closure ? $compiler : \Closure::fromCallable($compiler);
        $this->evaluator = $evaluator instanceof \Closure ? $evaluator : \Closure::fromCallable($evaluator);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return \Closure
     */
    public function getCompiler()
    {
        return $this->compiler;
    }

    /**
     * @return \Closure
     */
    public function getEvaluator()
    {
        return $this->evaluator;
    }

    /**
     * Creates an ExpressionFunction from a PHP function name.
     *
     * @param string|null $expressionFunctionName The expression function name (default: same than the PHP function name)
     *
     * @return self
     *
     * @throws \InvalidArgumentException if given PHP function name does not exist
     * @throws \InvalidArgumentException if given PHP function name is in namespace
     *                                   and expression function name is not defined
     */
    public static function fromPhp(string $phpFunctionName, string $expressionFunctionName = null)
    {
        $phpFunctionName = ltrim($phpFunctionName, '\\');
        if (!\function_exists($phpFunctionName)) {
            throw new \InvalidArgumentException(sprintf('PHP function "%s" does not exist.', $phpFunctionName));
        }

        $parts = explode('\\', $phpFunctionName);
        if (!$expressionFunctionName && \count($parts) > 1) {
            throw new \InvalidArgumentException(sprintf('An expression function name must be defined when PHP function "%s" is namespaced.', $phpFunctionName));
        }

        $compiler = function (...$args) use ($phpFunctionName) {
            return sprintf('\%s(%s)', $phpFunctionName, implode(', ', $args));
        };

        $evaluator = function ($p, ...$args) use ($phpFunctionName) {
            return $phpFunctionName(...$args);
        };

        return new self($expressionFunctionName ?: end($parts), $compiler, $evaluator);
    }
}
