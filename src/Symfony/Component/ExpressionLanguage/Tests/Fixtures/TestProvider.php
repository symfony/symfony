<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests\Fixtures;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class TestProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('identity', fn ($input) => $input, fn (array $values, $input) => $input),

            ExpressionFunction::fromPhp('strtoupper'),

            ExpressionFunction::fromPhp('\strtolower'),

            ExpressionFunction::fromPhp('Symfony\Component\ExpressionLanguage\Tests\Fixtures\fn_namespaced', 'fn_namespaced'),
        ];
    }
}

function fn_namespaced()
{
    return true;
}
