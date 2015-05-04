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

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Radu Murzea <radu.murzea@gmail.com>
 *
 * @api
 */
class DateTimeValidator extends DateValidator
{
    /**
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    const PATTERN = '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/';

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof DateTime) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\DateTime');
        }

        if (null === $value || '' === $value || $value instanceof \DateTime) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        $dateTimeObject = \DateTime::createFromFormat($constraint->format, $value);
        $errors = \DateTime::getLastErrors();

        //the value was successfully parsed
        if ($errors['error_count'] + $errors['warning_count'] <= 0) {
            return;
        }

        //see the function's phpdoc for details
        $errorCode = $this->getViolationCode($errors, 'errors');

        if (! is_null($errorCode)) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode($errorCode)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode($errorCode)
                    ->addViolation();
            }

            return;
        }

        //see the function's phpdoc for details
        $warningCode = $this->getViolationCode($errors, 'warnings');

        if (! is_null($warningCode)) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode($warningCode)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode($warningCode)
                    ->addViolation();
            }

            return;
        }
    }
    
    /**
     * PHP's DateTime stores the errors and warning at unpredictable indexes,
     * so it's a bit trickier to retrieve them (we have to use array_values + array_shift).
     * 
     * Also, based on the error/warning message string, we can guess if it was
     * a date problem, time problem or format/general problem.
     * 
     * @param array $errors result from calling \DateTime::getLastErrors()
     * @param string $key the key under which to search for errors/warnings
     * @return mixed an integer representing the error code or NULL if everything was ok
     */
    private function getViolationCode($errors, $key)
    {
        if (isset($errors[$key]) && count($errors[$key]) > 0) {
            $allErrors = array_values($errors[$key]);
            $firstError = array_shift($allErrors);

            if (strpos($firstError, 'date') !== false) {
                return DateTime::INVALID_DATE_ERROR;
            } elseif (strpos($firstError, 'time') !== false) {
                return DateTime::INVALID_TIME_ERROR;
            } else {
                return DateTime::INVALID_FORMAT_ERROR;
            }
        } else {
            return;
        }
    }
}
