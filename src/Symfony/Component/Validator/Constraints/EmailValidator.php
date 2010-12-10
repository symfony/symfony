<?php

namespace Symfony\Component\Validator\Constraints;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class EmailValidator extends ConstraintValidator
{
    const PATTERN = '/^([^@\s]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})$/i';

    public function isValid($value, Constraint $constraint)
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString()'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string)$value;

        if (!preg_match(self::PATTERN, $value)) {
            $this->setMessage($constraint->message, array('{{ value }}' => $value));

            return false;
        }

        if ($constraint->checkMX) {
            $host = substr($value, strpos($value, '@') + 1);

            if (!$this->checkMX($host)) {
                $this->setMessage($constraint->message, array('{{ value }}' => $value));

                return false;
            }
        }

        return true;
    }

    /**
     * Check DNS Records for MX type.
     *
     * @param string $host Host name
     *
     * @return boolean
     */
    private function checkMX($host)
    {
        if (function_exists('checkdnsrr')) {
            return checkdnsrr($host, 'MX');
        }

        throw new ValidatorError('Could not retrieve DNS record information. Remove check_mx = true to prevent this warning');
    }
}