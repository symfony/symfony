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

use LogicException;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Define some ExpressionLanguage functions.
 *
 * @author Ihor Khokhlov <eld2303@gmail.com>
 */
class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    private ExecutionContextInterface $context;

    public function __construct(ExecutionContextInterface $context)
    {
        $this->context = $context;
    }

    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('is_valid', function () {
                throw new LogicException('The "is_valid" function cannot be compiled.');
            }, function (array $variables, ...$arguments): bool {
                $context = $this->context;

                $validator = $context->getValidator()->inContext($context);

                $violations = $validator->validate(...$arguments)->getViolations();

                return 0 === $violations->count();
            }),
        ];
    }
}
