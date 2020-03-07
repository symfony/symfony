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
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class NotInRangeValidator extends ConstraintValidator
{
    private $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof NotInRange) {
            throw new UnexpectedTypeException($constraint, NotInRange::class);
        }

        if (null === $value) {
            return;
        }
        
        if (!is_numeric($value) && !$value instanceof \DateTimeInterface) {
            $this->context->buildViolation($constraint->invalidMessage)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setCode(NotInRange::INVALID_CHARACTERS_ERROR)
                ->addViolation();

            return;
        }
        
        if (null === $min = $this->getLimit($constraint->minPropertyPath, $constraint->min, $constraint)) {
            throw new ConstraintDefinitionException(sprintf('The min value cannot be null in the "%s" constraint.', \get_class($constraint)));
        }

        if (null === $max = $this->getLimit($constraint->maxPropertyPath, $constraint->max, $constraint)) {
            throw new ConstraintDefinitionException(sprintf('The max value cannot be null in the "%s" constraint.', \get_class($constraint)));
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
                    throw new ConstraintDefinitionException(sprintf('The min value "%s" could not be converted to a "%s" instance in the "%s" constraint.', $min, $dateTimeClass, \get_class($constraint)));
                }
            }

            if (\is_string($max)) {
                $dateTimeClass = $dateTimeClass ?: ($value instanceof \DateTimeImmutable ? \DateTimeImmutable::class : \DateTime::class);

                try {
                    $max = new $dateTimeClass($max);
                } catch (\Exception $e) {
                    throw new ConstraintDefinitionException(sprintf('The max value "%s" could not be converted to a "%s" instance in the "%s" constraint.', $max, $dateTimeClass, \get_class($constraint)));
                }
            }
        }

        if ($value >= $min && $value <= $max) {
            $violationBuilder = $this->context->buildViolation($constraint->inRangeMessage)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setParameter('{{ min }}', $this->formatValue($min, self::PRETTY_DATE))
                ->setParameter('{{ max }}', $this->formatValue($max, self::PRETTY_DATE))
                ->setCode(NotInRange::IN_RANGE_ERROR);

            if (null !== $constraint->maxPropertyPath) {
                $violationBuilder->setParameter('{{ max_limit_path }}', $constraint->maxPropertyPath);
            }

            if (null !== $constraint->minPropertyPath) {
                $violationBuilder->setParameter('{{ min_limit_path }}', $constraint->minPropertyPath);
            }

            $violationBuilder->addViolation();

            return;
        }
    }

    private function getLimit($propertyPath, $default, Constraint $constraint)
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
            throw new ConstraintDefinitionException(sprintf('Invalid property path "%s" provided to "%s" constraint: %s', $propertyPath, \get_class($constraint), $e->getMessage()), 0, $e);
        }
    }

    private function getPropertyAccessor(): PropertyAccessorInterface
    {
        if (null === $this->propertyAccessor) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor;
    }
}
