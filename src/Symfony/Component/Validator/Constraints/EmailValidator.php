<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class EmailValidator extends ConstraintValidator
{
    const PATTERN = '/^([^@\s]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})$/i';

    public function isValid($value, Constraint $constraint)
    {
        if ($value === null) {
            return true;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString()'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string)$value;

        if (!preg_match(self::PATTERN, $value)) {
            $this->setMessage($constraint->message, array('value' => $value));

            return false;
        }

        if ($constraint->checkMX) {
            $host = substr($value, strpos($value, '@') + 1);

            if (!$this->checkMX($host)) {
                $this->setMessage($constraint->message, array('value' => $value));

                return false;
            }
        }

        return true;
    }

    /**
     * Check DNA Records for MX type (from Doctrine EmailValidator)
     *
     * @param string $host Host name
     * @return boolean
     * @licence This software consists of voluntary contributions made by many individuals
     * and is licensed under the LGPL. For more information, see
     * <http://www.phpdoctrine.org>.
     */
    private function checkMX($host)
    {
        // We have different behavior here depending of OS and PHP version
        if (strtolower(substr(PHP_OS, 0, 3)) == 'win' && version_compare(PHP_VERSION, '5.3.0', '<'))  {
            $output = array();

            @exec('nslookup -type=MX '.escapeshellcmd($host) . ' 2>&1', $output);

            if (empty($output)) {
                throw new ValidatorError('Unable to execute DNS lookup. Are you sure PHP can call exec()?');
            }

            foreach ($output as $line) {
                if (preg_match('/^'.$host.'/', $line)) {
                    return true;
                }
            }

            return false;
        } else if (function_exists('checkdnsrr')) {
            return checkdnsrr($host, 'MX');
        }

        throw new ValidatorError('Could not retrieve DNS record information. Remove check_mx = true to prevent this warning');
    }
}