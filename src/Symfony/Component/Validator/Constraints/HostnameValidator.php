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
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 */
class HostnameValidator extends ConstraintValidator
{
    /**
     * https://tools.ietf.org/html/rfc2606.
     */
    private const RESERVED_TLDS = [
        'example',
        'invalid',
        'localhost',
        'test',
    ];

    /**
     * @return void
     */
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof Hostname) {
            throw new UnexpectedTypeException($constraint, Hostname::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;
        if ('' === $value) {
            return;
        }
        if (!$this->isValid($value) || ($constraint->requireTld && !$this->hasValidTld($value))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Hostname::INVALID_HOSTNAME_ERROR)
                ->addViolation();
        }
    }

    private function isValid(string $domain): bool
    {
        return false !== filter_var($domain, \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME);
    }

    private function hasValidTld(string $domain): bool
    {
        return str_contains($domain, '.') && !\in_array(substr($domain, strrpos($domain, '.') + 1), self::RESERVED_TLDS, true);
    }
}
