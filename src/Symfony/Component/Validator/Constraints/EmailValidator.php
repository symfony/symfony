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

class EmailValidator extends ConstraintValidator
{

    public function isValid($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString()'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
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
     * @return Boolean
     */
    private function checkMX($host)
    {
        if (function_exists('checkdnsrr')) {
            return checkdnsrr($host, 'MX');
        }

        throw new \LogicException('Could not retrieve DNS record information. Remove check_mx = true to prevent this warning');
    }
}
