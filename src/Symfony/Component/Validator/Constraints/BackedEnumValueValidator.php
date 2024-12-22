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

/**
 * BackedEnumValueValidator validates that a backed enum case can be hydrated from a value.
 *
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
class BackedEnumValueValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof BackedEnumValue) {
            throw new UnexpectedTypeException($constraint, BackedEnumValue::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        try {
            $enumTypeValue = $constraint->type::tryFrom($value);
        } catch (\TypeError) {
            $this->context->buildViolation($constraint->typeMessage)
                ->setParameter('{{ type }}', $this->formatValue((string) (new \ReflectionEnum($constraint->type))->getBackingType()))
                ->setCode(BackedEnumValue::INVALID_TYPE_ERROR)
                ->addViolation();

            return;
        }

        if (null === $enumTypeValue) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setParameter('{{ choices }}', $this->formatValidCases($constraint))
                ->setCode(BackedEnumValue::NO_SUCH_VALUE_ERROR)
                ->addViolation();

            return;
        }

        if (\count($constraint->except) > 0 && \in_array($enumTypeValue, $constraint->except, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($enumTypeValue->value))
                ->setParameter('{{ choices }}', $this->formatValidCases($constraint))
                ->setCode(BackedEnumValue::NO_SUCH_VALUE_ERROR)
                ->addViolation();
        }
    }

    private function formatValidCases(BackedEnumValue $constraint): string
    {
        return $this->formatValues(array_map(
            static fn (\BackedEnum $case) => $case->value,
            array_filter(
                $constraint->type::cases(),
                static fn (\BackedEnum $currentValue) => !\in_array($currentValue, $constraint->except, true),
            )
        ));
    }
}
