<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class TimestampValidator extends ConstraintValidator
{

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Timestamp){
            throw new UnexpectedTypeException($constraint::class, self::class);
        }

        if (null === $value){
            return;
        }

        if (!is_int($value)){
            throw new UnexpectedValueException($value, 'int');
        }

        if (0 > $value){
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->setInvalidValue($value)
                ->setCode(Timestamp::INVALID_TIMESTAMP_ERROR)
                ->addViolation();
        }

        // Not sure if there is value that can fall in this case
        $date = \DateTimeImmutable::createFromFormat('U', $value);
        if ($date === false){
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->setInvalidValue($value)
                ->setCode(Timestamp::INVALID_TIMESTAMP_ERROR)
                ->addViolation();
        }
        $timestamp = $value;

        if ($constraint->greaterThan && date_create($constraint->greaterThan)->getTimestamp() >= $timestamp){
            $this->context->buildViolation($constraint->greaterThanMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ compared_value }}', $this->formatValue(date_create($constraint->greaterThan), self::PRETTY_DATE))
                ->setInvalidValue($value)
                ->setCode(Timestamp::TOO_LOW_ERROR)
                ->addViolation();

            return;
        }

        if ($constraint->lessThan && date_create($constraint->lessThan)->getTimestamp() <= $timestamp){
            $this->context->buildViolation($constraint->lessThanMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ compared_value }}', $this->formatValue(date_create($constraint->lessThan), self::PRETTY_DATE))
                ->setInvalidValue($value)
                ->setCode(Timestamp::TOO_HIGH_ERROR)
                ->addViolation();

            return;
        }

        if ($constraint->greaterThanOrEqual &&
            date_create($constraint->greaterThanOrEqual)->getTimestamp() > $timestamp)
        {
            $this->context->buildViolation($constraint->greaterThanOrEqualMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ compared_value }}', $this->formatValue(date_create($constraint->greaterThanOrEqual), self::PRETTY_DATE))
                ->setInvalidValue($value)
                ->setCode(Timestamp::TOO_LOW_ERROR)
                ->addViolation();

            return;
        }

        if ($constraint->lessThanOrEqual &&
            date_create($constraint->lessThanOrEqual)->getTimestamp() < $timestamp)
        {
            $this->context->buildViolation($constraint->lessThanOrEqualMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ compared_value }}', $this->formatValue(date_create($constraint->lessThanOrEqual), self::PRETTY_DATE))
                ->setInvalidValue($value)
                ->setCode(Timestamp::TOO_HIGH_ERROR)
                ->addViolation();
        }
    }
}