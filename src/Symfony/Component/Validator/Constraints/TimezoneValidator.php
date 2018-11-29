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
 * Validates whether a value is a valid timezone identifier.
 *
 * @author Javier Spagnoletti <phansys@gmail.com>
 */
class TimezoneValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Timezone) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Timezone');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        // @see: https://bugs.php.net/bug.php?id=75928
        if ($constraint->countryCode) {
            $timezoneIds = \DateTimeZone::listIdentifiers($constraint->zone, $constraint->countryCode);
        } else {
            $timezoneIds = \DateTimeZone::listIdentifiers($constraint->zone);
        }

        if ($timezoneIds && !\in_array($value, $timezoneIds, true)) {
            if ($constraint->countryCode) {
                $code = Timezone::NO_SUCH_TIMEZONE_IN_COUNTRY_ERROR;
            } elseif (\DateTimeZone::ALL !== $constraint->zone) {
                $code = Timezone::NO_SUCH_TIMEZONE_IN_ZONE_ERROR;
            } else {
                $code = Timezone::NO_SUCH_TIMEZONE_ERROR;
            }

            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode($code)
                ->addViolation();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'zone';
    }

    /**
     * {@inheritdoc}
     */
    protected function formatValue($value, $format = 0)
    {
        $value = parent::formatValue($value, $format);
        if ($value) {
            if (\DateTimeZone::PER_COUNTRY !== $value) {
                $r = new \ReflectionClass(\DateTimeZone::class);
                $consts = $r->getConstants();
                if ($zoneFound = array_search($value, $consts, true)) {
                    return $zoneFound;
                }

                return $value;
            }
        }

        return $value;
    }
}
