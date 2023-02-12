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
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Provides a base class for the validation of property comparisons.
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractComparisonValidator extends ConstraintValidator
{
    private ?PropertyAccessorInterface $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * @return void
     */
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof AbstractComparison) {
            throw new UnexpectedTypeException($constraint, AbstractComparison::class);
        }

        if (null === $value) {
            return;
        }

        if ($path = $constraint->propertyPath) {
            if (null === $object = $this->context->getObject()) {
                return;
            }

            try {
                $comparedValue = $this->getPropertyAccessor()->getValue($object, $path);
            } catch (NoSuchPropertyException $e) {
                throw new ConstraintDefinitionException(sprintf('Invalid property path "%s" provided to "%s" constraint: ', $path, get_debug_type($constraint)).$e->getMessage(), 0, $e);
            }
        } else {
            $comparedValue = $constraint->value;
        }

        // Convert strings to DateTimes if comparing another DateTime
        // This allows to compare with any date/time value supported by
        // the DateTime constructor:
        // https://php.net/datetime.formats
        if (\is_string($comparedValue) && $value instanceof \DateTimeInterface) {
            // If $value is immutable, convert the compared value to a DateTimeImmutable too, otherwise use DateTime
            $dateTimeClass = $value instanceof \DateTimeImmutable ? \DateTimeImmutable::class : \DateTime::class;

            try {
                $comparedValue = new $dateTimeClass($comparedValue);
            } catch (\Exception) {
                throw new ConstraintDefinitionException(sprintf('The compared value "%s" could not be converted to a "%s" instance in the "%s" constraint.', $comparedValue, $dateTimeClass, get_debug_type($constraint)));
            }
        }

        if (!$this->compareValues($value, $comparedValue)) {
            $violationBuilder = $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value, self::OBJECT_TO_STRING | self::PRETTY_DATE))
                ->setParameter('{{ compared_value }}', $this->formatValue($comparedValue, self::OBJECT_TO_STRING | self::PRETTY_DATE))
                ->setParameter('{{ compared_value_type }}', $this->formatTypeOf($comparedValue))
                ->setCode($this->getErrorCode());

            if (null !== $path) {
                $violationBuilder->setParameter('{{ compared_value_path }}', $path);
            }

            $violationBuilder->addViolation();
        }
    }

    private function getPropertyAccessor(): PropertyAccessorInterface
    {
        return $this->propertyAccessor ??= PropertyAccess::createPropertyAccessor();
    }

    /**
     * Compares the two given values to find if their relationship is valid.
     */
    abstract protected function compareValues(mixed $value1, mixed $value2): bool;

    /**
     * Returns the error code used if the comparison fails.
     */
    protected function getErrorCode(): ?string
    {
        return null;
    }
}
