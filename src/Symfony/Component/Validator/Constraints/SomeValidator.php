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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
class SomeValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof Some) {
            throw new UnexpectedTypeException($constraint, Some::class);
        }

        if (null === $value) {
            return;
        }

        if (!is_iterable($value)) {
            throw new UnexpectedValueException($value, 'iterable');
        }

        $validator = $this->context->getValidator();
        $atLeastOneOf = new AtLeastOneOf([
            'constraints' => $constraint->constraints,
            'message' => '',
            'includeInternalMessages' => $constraint->includeInternalMessages,
        ]);
        $validValues = 0;
        $violationMessages = [];

        foreach ($value as $element) {
            $executionContext = clone $this->context;
            $executionContext->setNode($element, $this->context->getObject(), $this->context->getMetadata(), $this->context->getPropertyPath());
            $violations = $validator->inContext($executionContext)->validate($element, $atLeastOneOf, $this->context->getGroup())->getViolations();

            if (0 === \count($violations)) {
                ++$validValues;
            } elseif (0 === \count($violationMessages)) {
                foreach ($violations as $violation) {
                    $violationMessages[] = $violation->getMessage();
                }
            }
        }

        if ($constraint->min === $constraint->max && $validValues !== $constraint->min) {
            $this->context->buildViolation($this->buildMessage($constraint->exactMessage, $violationMessages))
                ->setParameter('{{ count }}', $this->formatValue($validValues))
                ->setParameter('{{ limit }}', $this->formatValue($constraint->min))
                ->setPlural($constraint->min)
                ->setInvalidValue($value)
                ->setCode(Some::SOME_EXACTLY_ERROR)
                ->addViolation();
        } elseif (null !== $constraint->max && $validValues > $constraint->max) {
            $this->context->buildViolation($this->buildMessage($constraint->maxMessage, $violationMessages))
                ->setParameter('{{ count }}', $this->formatValue($validValues))
                ->setParameter('{{ limit }}', $this->formatValue($constraint->max))
                ->setPlural($constraint->max)
                ->setInvalidValue($value)
                ->setCode(Some::SOME_TOO_MANY_ERROR)
                ->addViolation();
        } elseif ($validValues < $constraint->min) {
            $this->context->buildViolation($this->buildMessage($constraint->minMessage, $violationMessages))
                ->setParameter('{{ count }}', $this->formatValue($validValues))
                ->setParameter('{{ limit }}', $this->formatValue($constraint->min))
                ->setPlural($constraint->min)
                ->setInvalidValue($value)
                ->setCode(Some::SOME_TOO_FEW_ERROR)
                ->addViolation();
        }
    }

    private function buildMessage(string $message, array $violationMessages): string
    {
        if (!str_contains($message, '|')) {
            return implode('', [$message, ...$violationMessages]);
        }

        $messageParts = explode('|', $message);
        $messageParts = array_map(static fn (string $part) => implode('', [$part, ...$violationMessages]), $messageParts);

        return implode('|', $messageParts);
    }
}
