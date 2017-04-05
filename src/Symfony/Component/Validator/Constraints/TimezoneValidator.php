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

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;
        $zone = null !== $constraint->zone ? $constraint->zone : \DateTimeZone::ALL;
        $timezoneIds = \DateTimeZone::listIdentifiers($zone, $constraint->countryCode);

        if ($timezoneIds && !in_array($value, $timezoneIds, true)) {
            if ($constraint->countryCode) {
                $code = Timezone::NO_SUCH_TIMEZONE_IN_COUNTRY_ERROR;
            } elseif (null !== $constraint->zone) {
                $code = Timezone::NO_SUCH_TIMEZONE_IN_ZONE_ERROR;
            } else {
                $code = Timezone::NO_SUCH_TIMEZONE_ERROR;
            }

            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ extra_info }}', $this->formatExtraInfo($constraint->zone, $constraint->countryCode))
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
     * Format the extra info which is appended to validation message based on
     * constraint options.
     *
     * @param int|null    $zone
     * @param string|null $countryCode
     *
     * @return string
     */
    private function formatExtraInfo($zone, $countryCode = null)
    {
        if (null === $zone) {
            return '';
        }
        if ($countryCode) {
            $value = ' for ISO 3166-1 country code "'.$countryCode.'"';
        } else {
            $r = new \ReflectionClass('\DateTimeZone');
            $consts = $r->getConstants();
            if ($value = array_search($zone, $consts, true)) {
                $value = ' for "'.$value.'" zone';
            } else {
                $value = ' for zone with identifier '.$zone;
            }
        }

        return $this->formatValue($value);
    }
}
