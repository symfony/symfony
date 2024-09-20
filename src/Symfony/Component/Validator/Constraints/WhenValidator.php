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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class WhenValidator extends ConstraintValidator
{
    public function __construct(private ?ExpressionLanguage $expressionLanguage = null)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof When) {
            throw new UnexpectedTypeException($constraint, When::class);
        }

        $context = $this->context;
        $variables = $constraint->values;
        $variables['value'] = $value;
        $variables['this'] = $context->getObject();

        if ($this->getExpressionLanguage()->evaluate($constraint->expression, $variables)) {
            $context->getValidator()->inContext($context)
                ->validate($value, $constraint->constraints);
        }
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        if (!class_exists(ExpressionLanguage::class)) {
            throw new LogicException(\sprintf('The "symfony/expression-language" component is required to use the "%s" validator. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        return $this->expressionLanguage ??= new ExpressionLanguage();
    }
}
