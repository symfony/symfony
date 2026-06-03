<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('is_valid', static fn (...$arguments) => \sprintf(
                '0 === $context->getValidator()->inContext($context)->validate(%s)->getViolations()->count()',
                implode(', ', $arguments)
            ), static fn (array $variables, ...$arguments): bool => 0 === $variables['context']->getValidator()->inContext($variables['context'])->validate(...$arguments)->getViolations()->count()),
        ];
    }
}
