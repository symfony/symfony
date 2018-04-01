<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\ExpressionLanguage\Tests\Fixtures;

use Symphony\Component\ExpressionLanguage\ExpressionFunction;
use Symphony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symphony\Component\ExpressionLanguage\ExpressionPhpFunction;

class TestProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        return array(
            new ExpressionFunction('identity', function ($input) {
                return $input;
            }, function (array $values, $input) {
                return $input;
            }),

            ExpressionFunction::fromPhp('strtoupper'),

            ExpressionFunction::fromPhp('\strtolower'),

            ExpressionFunction::fromPhp('Symphony\Component\ExpressionLanguage\Tests\Fixtures\fn_namespaced', 'fn_namespaced'),
        );
    }
}

function fn_namespaced()
{
    return true;
}
