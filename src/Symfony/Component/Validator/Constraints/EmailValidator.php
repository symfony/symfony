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
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class EmailValidator extends ConstraintValidator
{
    /**
     * isStrict
     *
     * @var bool
     */
    private $isStrict;

    public function __construct($strict = false)
    {
        $this->isStrict = $strict;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Email) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Email');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        if (null === $constraint->strict) {
            $constraint->strict = $this->isStrict;
        }

        if ($constraint->strict) {
            if (!class_exists('\Egulias\EmailValidator\EmailValidator')) {
                throw new RuntimeException('Strict email validation requires egulias/email-validator');
            }

            $strictValidator = new \Egulias\EmailValidator\EmailValidator();

            if (!$strictValidator->isValid($value, false, true)) {
                $this->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode(Email::INVALID_FORMAT_ERROR)
                    ->addViolation();

                return;
            }
        } elseif (!preg_match('/.+\@.+\..+/', $value)) {
            $this->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Email::INVALID_FORMAT_ERROR)
                ->addViolation();

            return;
        }

        $host = substr($value, strpos($value, '@') + 1);

        // Check for host DNS resource records
        if ($constraint->checkMX) {
            if (!$this->checkMX($host)) {
                $this->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode(Email::MX_CHECK_FAILED_ERROR)
                    ->addViolation();
            }

            return;
        }

        if ($constraint->checkHost && !$this->checkHost($host)) {
            $this->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Email::HOST_CHECK_FAILED_ERROR)
                ->addViolation();
        }
    }

    /**
     * Check DNS Records for MX type.
     *
     * @param string $host Host
     *
     * @return bool
     */
    private function checkMX($host)
    {
        return checkdnsrr($host, 'MX');
    }

    /**
     * Check if one of MX, A or AAAA DNS RR exists.
     *
     * @param string $host Host
     *
     * @return bool
     */
    private function checkHost($host)
    {
        return $this->checkMX($host) || (checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA'));
    }
}
