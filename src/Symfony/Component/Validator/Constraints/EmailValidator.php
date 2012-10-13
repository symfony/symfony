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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class EmailValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;
        $valid = filter_var($value, FILTER_VALIDATE_EMAIL);

        if ($valid) {
            $host = substr($value, strpos($value, '@') + 1);

            if (version_compare(PHP_VERSION, '5.3.3', '<') && strpos($host, '.') === false) {
                // Likely not a FQDN, bug in PHP FILTER_VALIDATE_EMAIL prior to PHP 5.3.3
                $valid = false;
            }

            // Check for host DNS resource records
            if ($valid && $constraint->checkMX) {
                $valid = $this->checkMX($host);
            } elseif ($valid && $constraint->checkHost) {
                $valid = $this->checkHost($host);
            }
        }

        if (!$valid) {
            $this->context->addViolation($constraint->message, array('{{ value }}' => $value));
        }
    }

    /**
     * Check DNS Records for MX type.
     *
     * @param string $host Hostname
     *
     * @return Boolean
     */
    private function checkMX($host)
    {
        return checkdnsrr($host, 'MX');
    }

    /**
     * Check if one of MX, A or AAAA DNS RR exists.
     *
     * @param string $host Hostname
     *
     * @return Boolean
     */
    private function checkHost($host)
    {
        return $this->checkMX($host) || (checkdnsrr($host, "A") || checkdnsrr($host, "AAAA"));
    }
}
