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

        // @see: https://bugs.php.net/bug.php?id=75928
        if ($constraint->countryCode) {
            $timezoneIds = \DateTimeZone::listIdentifiers($zone, $constraint->countryCode);
        } else {
            $timezoneIds = \DateTimeZone::listIdentifiers($zone);
        }

        if ($timezoneIds && !in_array($value, $timezoneIds, true)) {
            if ($constraint->countryCode) {
                $code = Timezone::NO_SUCH_TIMEZONE_IN_COUNTRY_ERROR;
            } elseif (null !== $constraint->zone) {
                $code = Timezone::NO_SUCH_TIMEZONE_IN_ZONE_ERROR;
            } else {
                $code = Timezone::NO_SUCH_TIMEZONE_ERROR;
            }

            $violation = $this->context->buildViolation($constraint->message);

            foreach ($this->generateValidationMessage($constraint->zone, $constraint->countryCode) as $placeholder => $message) {
                $violation->setParameter($placeholder, $message);
            }

            $violation
                ->setCode($code)
                ->addViolation()
            ;
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
     * Generates the replace parameters which are used in validation message.
     *
     * @param int|null    $zone
     * @param string|null $countryCode
     *
     * @return array
     */
    private function generateValidationMessage(int $zone = null, string $countryCode = null): array
    {
        $values = array(
            '{{ country_code_message }}' => '',
            '{{ zone_message }}' => '',
        );

        if (null !== $zone) {
            if (\DateTimeZone::PER_COUNTRY !== $zone) {
                $r = new \ReflectionClass(\DateTimeZone::class);
                $consts = $r->getConstants();
                if ($zoneFound = array_search($zone, $consts, true)) {
                    $values['{{ zone_message }}'] = ' at "'.$zoneFound.'" zone';
                } else {
                    $values['{{ zone_message }}'] = ' at zone with identifier '.$zone;
                }
            }
            if ($countryCode) {
                $values['{{ country_code_message }}'] = ' for ISO 3166-1 country code "'.$countryCode.'"';
            }
        }

        return $values;
    }
}
