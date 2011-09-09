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
 * @author Joseph Bielawski <stloyd@gmail.com>
 *
 * @api
 */
class IpValidator extends ConstraintValidator
{
    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @return Boolean Whether or not the value is valid
     *
     * @api
     */
    public function isValid($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        switch ($constraint->version) {
            case Ip::V4:
               $flag = FILTER_FLAG_IPV4;
               break;

            case Ip::V6:
               $flag = FILTER_FLAG_IPV6;
               break;

            case Ip::V4_NO_PRIV:
               $flag = FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE;
               break;

            case Ip::V6_NO_PRIV:
               $flag = FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE;
               break;

            case Ip::ALL_NO_PRIV:
               $flag = FILTER_FLAG_NO_PRIV_RANGE;
               break;

            case Ip::V4_NO_RES:
               $flag = FILTER_FLAG_IPV4 | FILTER_FLAG_NO_RES_RANGE;
               break;

            case Ip::V6_NO_RES:
               $flag = FILTER_FLAG_IPV6 | FILTER_FLAG_NO_RES_RANGE;
               break;

            case Ip::ALL_NO_RES:
               $flag = FILTER_FLAG_NO_RES_RANGE;
               break;

            case Ip::V4_ONLY_PUBLIC:
               $flag = FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
               break;

            case Ip::V6_ONLY_PUBLIC:
               $flag = FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
               break;

            case Ip::ALL_ONLY_PUBLIC:
               $flag = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
               break;

            default:
                $flag = null;
                break;
        }

        if (!filter_var($value, FILTER_VALIDATE_IP, $flag)) {
            $this->setMessage($constraint->message, array('{{ value }}' => $value));

            return false;
        }

        return true;
    }
}
