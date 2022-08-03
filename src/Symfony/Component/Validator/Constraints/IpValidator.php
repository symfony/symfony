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

/**
 * Validates whether a value is a valid IP address.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
class IpValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof Ip) {
            throw new UnexpectedTypeException($constraint, Ip::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        if (null !== $constraint->normalizer) {
            $value = ($constraint->normalizer)($value);
        }

        $flag = match ($constraint->version) {
            Ip::V4 => \FILTER_FLAG_IPV4,
            Ip::V6 => \FILTER_FLAG_IPV6,
            Ip::V4_NO_PRIV => \FILTER_FLAG_IPV4 | \FILTER_FLAG_NO_PRIV_RANGE,
            Ip::V6_NO_PRIV => \FILTER_FLAG_IPV6 | \FILTER_FLAG_NO_PRIV_RANGE,
            Ip::ALL_NO_PRIV => \FILTER_FLAG_NO_PRIV_RANGE,
            Ip::V4_NO_RES => \FILTER_FLAG_IPV4 | \FILTER_FLAG_NO_RES_RANGE,
            Ip::V6_NO_RES => \FILTER_FLAG_IPV6 | \FILTER_FLAG_NO_RES_RANGE,
            Ip::ALL_NO_RES => \FILTER_FLAG_NO_RES_RANGE,
            Ip::V4_ONLY_PUBLIC => \FILTER_FLAG_IPV4 | \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
            Ip::V6_ONLY_PUBLIC => \FILTER_FLAG_IPV6 | \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
            Ip::ALL_ONLY_PUBLIC => \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
            default => 0,
        };

        if (!filter_var($value, \FILTER_VALIDATE_IP, $flag)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Ip::INVALID_IP_ERROR)
                ->addViolation();
        }
    }
}
