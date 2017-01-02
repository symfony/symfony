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
 * Represents a PHP function that can be used in an expression.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class ExpressionPhpFunction extends ExpressionFunction
{
    /**
     * Constructor.
     *
     * @param string $name The PHP function name
     *
     * @throws \InvalidArgumentException if given function name does not exist
     */
    public function __construct($name)
    {
        if (!function_exists($name)) {
            throw new \InvalidArgumentException(sprintf('PHP function "%s" does not exist.', $name));
        }

        $compiler = function () use ($name) {
            return sprintf('%s(%s)', $name, implode(', ', func_get_args()));
        };

        $evaluator = function () use ($name) {
            $args = func_get_args();

            return call_user_func_array($name, array_splice($args, 1));
        };

        parent::__construct($name, $compiler, $evaluator);
    }
}
