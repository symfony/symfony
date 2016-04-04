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
 */
class EmailValidator extends ConstraintValidator
{
    /**
     * @var string
     */
    private $defaultProfile;

    /**
     * @param bool   $strict  Deprecated. If the constraint does not define a
     *                        validation profile, this will determine if
     *                        rfc-no-warn should be used as the default profile.
     * @param string $profile If the constraint does not define a validation
     *                        profile, this will specify which profile to use.
     */
    public function __construct($strict = false, $profile = Email::PROFILE_BASIC_REGX)
    {
        $this->defaultProfile = $strict
            ? Email::PROFILE_RFC_DISALLOW_WARNINGS
            : $profile;
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

        if (isset($constraint->strict)) {
            $constraint->profile = $constraint->strict
                ? Email::PROFILE_RFC_DISALLOW_WARNINGS
                : Email::PROFILE_BASIC_REGX;
        }

        if (null === $constraint->profile) {
            $constraint->profile = $this->defaultProfile;
        }

        // Determine if the email address is valid
        switch ($constraint->profile) {
            case Email::PROFILE_BASIC_REGX:
            case Email::PROFILE_HTML5_REGX:
                $regex = ($constraint->profile === Email::PROFILE_BASIC_REGX)
                    ? '/^.+\@\S+\.\S+$/'
                    : '/^[a-zA-Z0-9.!#$%&’*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/';
                $emailAddressIsValid = (bool) preg_match($regex, $value);
                break;
            case Email::PROFILE_RFC_ALLOW_WARNINGS:
            case Email::PROFILE_RFC_DISALLOW_WARNINGS:
                if (!class_exists('\Egulias\EmailValidator\EmailValidator')) {
                    throw new RuntimeException(
                        'Standards-compliant email validation requires egulias/email-validator'
                    );
                }
                $rfcValidator = new \Egulias\EmailValidator\EmailValidator();
                $emailAddressIsValid = $rfcValidator->isValid(
                    $value,
                    false,
                    $constraint->profile === Email::PROFILE_RFC_DISALLOW_WARNINGS
                );
                break;
            default:
                throw new RuntimeException('Unrecognized email validation profile');
        }

        if (!$emailAddressIsValid) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Email::INVALID_FORMAT_ERROR)
                ->addViolation();

            return;
        }

        $host = substr($value, strrpos($value, '@') + 1);

        // Check for host DNS resource records
        if ($constraint->checkMX) {
            if (!$this->checkMX($host)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode(Email::MX_CHECK_FAILED_ERROR)
                    ->addViolation();
            }

            return;
        }

        if ($constraint->checkHost && !$this->checkHost($host)) {
            $this->context->buildViolation($constraint->message)
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
