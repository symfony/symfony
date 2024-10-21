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
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Andrey Sevastianov <mrpkmail@gmail.com>
 */
class ExpressionSyntaxValidator extends ConstraintValidator
{
    public function __construct(
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExpressionSyntax) {
            throw new UnexpectedTypeException($constraint, ExpressionSyntax::class);
        }

        if (\is_array($constraint->allowedVariables) && $constraint->allowedVariablesCallback) {
            throw new ConstraintDefinitionException('Either "allowedVariables" or "allowedVariablesCallback" must be specified on constraint ExpressionSyntax.');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        if ($constraint->allowedVariablesCallback) {
            if (!\is_callable($allowedVariables = [$this->context->getObject(), $constraint->allowedVariablesCallback])
                && !\is_callable($allowedVariables = [$this->context->getClassName(), $constraint->allowedVariablesCallback])
                && !\is_callable($allowedVariables = $constraint->allowedVariablesCallback)
            ) {
                throw new ConstraintDefinitionException('The ExpressionSyntax constraint expects a valid callback.');
            }
            $allowedVariables = $allowedVariables();
        } else {
            $allowedVariables = $constraint->allowedVariables;
        }

        $this->expressionLanguage ??= new ExpressionLanguage();

        try {
            $this->expressionLanguage->lint($value, $allowedVariables);
        } catch (SyntaxError $exception) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ syntax_error }}', $this->formatValue($exception->getMessage()))
                ->setInvalidValue((string) $value)
                ->setCode(ExpressionSyntax::EXPRESSION_SYNTAX_ERROR)
                ->addViolation();
        }
    }
}
