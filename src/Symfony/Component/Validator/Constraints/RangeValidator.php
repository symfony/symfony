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

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RangeValidator extends ConstraintValidator
{
    private $propertyAccessor;

    public function __construct(?PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Range) {
            throw new UnexpectedTypeException($constraint, Range::class);
        }

        if (null === $value) {
            return;
        }

        $min = $this->getLimit($constraint->minPropertyPath, $constraint->min, $constraint);
        $max = $this->getLimit($constraint->maxPropertyPath, $constraint->max, $constraint);

        if (!is_numeric($value) && !$value instanceof \DateTimeInterface) {
            if ($this->isParsableDatetimeString($min) && $this->isParsableDatetimeString($max)) {
                $this->context->buildViolation($constraint->invalidDateTimeMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                    ->setCode(Range::INVALID_CHARACTERS_ERROR)
                    ->addViolation();
            } else {
                $this->context->buildViolation($constraint->invalidMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                    ->setCode(Range::INVALID_CHARACTERS_ERROR)
                    ->addViolation();
            }

            return;
        }

        // Convert strings to DateTimes if comparing another DateTime
        // This allows to compare with any date/time value supported by
        // the DateTime constructor:
        // https://php.net/datetime.formats
        if ($value instanceof \DateTimeInterface) {
            $dateTimeClass = null;

            if (\is_string($min)) {
                $dateTimeClass = $value instanceof \DateTimeImmutable ? \DateTimeImmutable::class : \DateTime::class;

                try {
                    $min = new $dateTimeClass($min);
                } catch (\Exception $e) {
                    throw new ConstraintDefinitionException(sprintf('The min value "%s" could not be converted to a "%s" instance in the "%s" constraint.', $min, $dateTimeClass, get_debug_type($constraint)));
                }
            }

            if (\is_string($max)) {
                $dateTimeClass = $dateTimeClass ?: ($value instanceof \DateTimeImmutable ? \DateTimeImmutable::class : \DateTime::class);

                try {
                    $max = new $dateTimeClass($max);
                } catch (\Exception $e) {
                    throw new ConstraintDefinitionException(sprintf('The max value "%s" could not be converted to a "%s" instance in the "%s" constraint.', $max, $dateTimeClass, get_debug_type($constraint)));
                }
            }
        }

        $hasLowerLimit = null !== $min;
        $hasUpperLimit = null !== $max;

        if ($hasLowerLimit && $hasUpperLimit && ($value < $min || $value > $max)) {
            $message = $constraint->notInRangeMessage;
            $code = Range::NOT_IN_RANGE_ERROR;

            if ($value < $min && $constraint->deprecatedMinMessageSet) {
                $message = $constraint->minMessage;
                $code = Range::TOO_LOW_ERROR;
            }

            if ($value > $max && $constraint->deprecatedMaxMessageSet) {
                $message = $constraint->maxMessage;
                $code = Range::TOO_HIGH_ERROR;
            }

            $violationBuilder = $this->context->buildViolation($message)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setParameter('{{ min }}', $this->formatValue($min, self::PRETTY_DATE))
                ->setParameter('{{ max }}', $this->formatValue($max, self::PRETTY_DATE))
                ->setCode($code);

            if (null !== $constraint->maxPropertyPath) {
                $violationBuilder->setParameter('{{ max_limit_path }}', $constraint->maxPropertyPath);
            }

            if (null !== $constraint->minPropertyPath) {
                $violationBuilder->setParameter('{{ min_limit_path }}', $constraint->minPropertyPath);
            }

            $violationBuilder->addViolation();

            return;
        }

        if ($hasUpperLimit && $value > $max) {
            $violationBuilder = $this->context->buildViolation($constraint->maxMessage)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setParameter('{{ limit }}', $this->formatValue($max, self::PRETTY_DATE))
                ->setCode(Range::TOO_HIGH_ERROR);

            if (null !== $constraint->maxPropertyPath) {
                $violationBuilder->setParameter('{{ max_limit_path }}', $constraint->maxPropertyPath);
            }

            if (null !== $constraint->minPropertyPath) {
                $violationBuilder->setParameter('{{ min_limit_path }}', $constraint->minPropertyPath);
            }

            $violationBuilder->addViolation();

            return;
        }

        if ($hasLowerLimit && $value < $min) {
            $violationBuilder = $this->context->buildViolation($constraint->minMessage)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setParameter('{{ limit }}', $this->formatValue($min, self::PRETTY_DATE))
                ->setCode(Range::TOO_LOW_ERROR);

            if (null !== $constraint->maxPropertyPath) {
                $violationBuilder->setParameter('{{ max_limit_path }}', $constraint->maxPropertyPath);
            }

            if (null !== $constraint->minPropertyPath) {
                $violationBuilder->setParameter('{{ min_limit_path }}', $constraint->minPropertyPath);
            }

            $violationBuilder->addViolation();
        }
    }

    private function getLimit(?string $propertyPath, $default, Constraint $constraint)
    {
        if (null === $propertyPath) {
            return $default;
        }

        if (null === $object = $this->context->getObject()) {
            return $default;
        }

        try {
            return $this->getPropertyAccessor()->getValue($object, $propertyPath);
        } catch (NoSuchPropertyException $e) {
            throw new ConstraintDefinitionException(sprintf('Invalid property path "%s" provided to "%s" constraint: ', $propertyPath, get_debug_type($constraint)).$e->getMessage(), 0, $e);
        } catch (UninitializedPropertyException $e) {
            return null;
        }
    }

    private function getPropertyAccessor(): PropertyAccessorInterface
    {
        if (null === $this->propertyAccessor) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor;
    }

    private function isParsableDatetimeString($boundary): bool
    {
        if (null === $boundary) {
            return true;
        }

        if (!\is_string($boundary)) {
            return false;
        }

        try {
            new \DateTime($boundary);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
