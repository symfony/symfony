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
 * Validates whether a value is a valid IP address
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class IpValidator extends ConstraintValidator
{
    /**
     * @inheritDoc
     */
    public function isValid($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString()'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;
        $valid = false;

        if ($constraint->version == Ip::V4 || $constraint->version == Ip::ALL) {
            $valid = $this->isValidV4($value);
        }

        if ($constraint->version == Ip::V6 || $constraint->version == Ip::ALL) {
            $valid = $valid || $this->isValidV6($value);
        }

        if (!$valid) {
            $this->setMessage($constraint->message, array('{{ value }}' => $value));

            return false;
        }

        return true;
    }

    /**
     * Validates that a value is a valid IPv4 address
     *
     * @param string $value
     */
    protected function isValidV4($value)
    {
        if (!preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $value, $matches)) {
            return false;
        }

        for ($i = 1; $i <= 4; ++$i) {
            if ($matches[$i] > 255) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates that a value is a valid IPv6 address
     *
     * @param string $value
     */
    protected function isValidV6($value)
    {
        if (!preg_match('/^[0-9a-fA-F]{0,4}(:[0-9a-fA-F]{0,4}){1,5}((:[0-9a-fA-F]{0,4}){1,2}|:([\d\.]+))$/', $value, $matches)) {
            return false;
        }

        // allow V4 addresses mapped to V6
        if (isset($matches[4]) && !$this->isValidV4($matches[4])) {
            return false;
        }

        // "::" is only allowed once per address
        if (($offset = strpos($value, '::')) !== false) {
            if (strpos($value, '::', $offset + 1) !== false) {
                return false;
            }
        }

        return true;
    }
}