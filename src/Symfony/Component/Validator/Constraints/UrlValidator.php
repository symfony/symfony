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
 * Validates whether a value is a valid URL
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Igor Wiedler
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
class UrlValidator extends ConstraintValidator
{
    const PATTERN = '~^
            (?:\S+)://                               # protocol
            (?:
                ([[:alpha:]-]{1,64}\.)+([:alpha:]{2,9})  # a domain name
                    |                                #  or
                \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}       # a IP address
            )
            (:[0-9]{0,5})?                          # a port (optional)
            (/?|/\S+)?                              # a /, nothing or a / with something (optional)
        $~ixu';

    public function isValid($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;
        $valid = false;
        $ip    = null;

        // URL can must have at least 8 and valid protocol
        if (strlen($value) > 8 && (false !== $protocolPos = strpos($value, '://'))) {
            $valid = in_array(strtolower(substr($value, 0, $protocolPos)), $constraint->protocols);

            if ($valid) {
                // Check for an IPv6 address in URL
                if ((false !== $firstBracert = strpos($value, '://[')) && (false !== $secondBracert = strpos($value, ']'))) {
                    $ip = substr($value, $firstBracert += 4, $secondBracert - $firstBracert);
                    $valid = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
                }

                // If IPv6 exists valid, lets replace it and check the rest of value
                $valid = preg_match(self::PATTERN, ($ip === null ? $value : str_replace('['.$ip.']', 'example.com', $value)));
            }
        }

        if (!$valid) {
            $this->setMessage($constraint->message, array('{{ value }}' => $value));

            return false;
        }

        return true;
    }
}