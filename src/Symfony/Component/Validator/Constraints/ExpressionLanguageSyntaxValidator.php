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
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

trigger_deprecation('symfony/validator', '6.1', 'The "%s" constraint is deprecated since symfony 6.1, use "ExpressionSyntaxValidator" instead.', ExpressionLanguageSyntaxValidator::class);

/**
 * @author Andrey Sevastianov <mrpkmail@gmail.com>
 *
 * @deprecated since symfony 6.1, use ExpressionSyntaxValidator instead
 */
class ExpressionLanguageSyntaxValidator extends ConstraintValidator
{
    private ?ExpressionLanguage $expressionLanguage;

    public function __construct(?ExpressionLanguage $expressionLanguage = null)
    {
        if (!class_exists(ExpressionLanguage::class)) {
            throw new \LogicException(sprintf('The "%s" class requires the "ExpressionLanguage" component. Try running "composer require symfony/expression-language".', self::class));
        }

        $this->expressionLanguage = $expressionLanguage;
    }

    public function validate(mixed $expression, Constraint $constraint): void
    {
        if (!$constraint instanceof ExpressionLanguageSyntax) {
            throw new UnexpectedTypeException($constraint, ExpressionLanguageSyntax::class);
        }

        if (!\is_string($expression)) {
            throw new UnexpectedValueException($expression, 'string');
        }

        $this->expressionLanguage ??= new ExpressionLanguage();

        try {
            $this->expressionLanguage->lint($expression, $constraint->allowedVariables);
        } catch (SyntaxError $exception) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ syntax_error }}', $this->formatValue($exception->getMessage()))
                ->setInvalidValue((string) $expression)
                ->setCode(ExpressionLanguageSyntax::EXPRESSION_LANGUAGE_SYNTAX_ERROR)
                ->addViolation();
        }
    }
}
