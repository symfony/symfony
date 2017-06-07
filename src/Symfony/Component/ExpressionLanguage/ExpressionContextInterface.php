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
 * Allows to compile and evaluate expressions within a specific context.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
interface ExpressionContextInterface
{
    /**
     * The variable names used.
     *
     * @return array
     */
    public static function getNames();

    /**
     * Register functions to ExpressionLanguage.
     *
     * @param ExpressionLanguage $expressionLanguage
     */
    public static function registerFunctions(ExpressionLanguage $expressionLanguage);

    /**
     * Parses an expression.
     *
     * @param Expression|string       $expression         The expression to parse
     * @param ExpressionLanguage|null $expressionLanguage Optional instance of ExpressionLanguage to use
     *
     * @return ParsedExpression A ParsedExpression instance
     */
    public static function parse($expression, ExpressionLanguage $expressionLanguage = null);

    /**
     * Compiles an expression source code.
     *
     * @param Expression|string       $expression         The expression to compile
     * @param ExpressionLanguage|null $expressionLanguage Optional instance of ExpressionLanguage to use
     *
     * @return string The compiled PHP source code
     */
    public static function compile($expression, ExpressionLanguage $expressionLanguage = null);

    /**
     * Evaluate an expression.
     *
     * @param Expression|string $expression The expression to compile
     *
     * @return string The result of the evaluation of the expression
     */
    public function evaluate($expression);
}
