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
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class CidrValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof Cidr) {
            throw new UnexpectedTypeException($constraint, Cidr::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $cidrParts = explode('/', $value, 2);

        if (!isset($cidrParts[1])
            || !ctype_digit($cidrParts[1])
            || '' === $cidrParts[0]
        ) {
            $this->context
                ->buildViolation($constraint->message)
                ->setCode(Cidr::INVALID_CIDR_ERROR)
                ->addViolation();

            return;
        }

        $ipAddress = $cidrParts[0];
        $netmask = (int) $cidrParts[1];

        $validV4 = Ip::V6 !== $constraint->version
            && filter_var($ipAddress, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)
            && $netmask <= 32;

        $validV6 = Ip::V4 !== $constraint->version
            && filter_var($ipAddress, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6);

        if (!$validV4 && !$validV6) {
            $this->context
                ->buildViolation($constraint->message)
                ->setCode(Cidr::INVALID_CIDR_ERROR)
                ->addViolation();

            return;
        }

        if ($netmask < $constraint->netmaskMin || $netmask > $constraint->netmaskMax) {
            $this->context
                ->buildViolation($constraint->netmaskRangeViolationMessage)
                ->setParameter('{{ min }}', $constraint->netmaskMin)
                ->setParameter('{{ max }}', $constraint->netmaskMax)
                ->setCode(Cidr::OUT_OF_RANGE_ERROR)
                ->addViolation();
        }
    }
}
