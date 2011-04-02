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

class UrlValidator extends ConstraintValidator
{
    const PATTERN = '~^
            (%s)://                                 # protocol
            (
                ([a-z0-9-]+\.)+[a-z]{2,6}             # a domain name
                    |                                   #  or
                \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}    # a IP address
            )
            (:[0-9]+)?                              # a port (optional)
            (/?|/\S+)                               # a /, nothing or a / with something
        $~ix';

    public function isValid($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString()'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        $pattern = sprintf(self::PATTERN, implode('|', $constraint->protocols));

        if (!preg_match($pattern, $value)) {
            $this->setMessage($constraint->message, array('{{ value }}' => $value));

            return false;
        }

        return true;
    }
}