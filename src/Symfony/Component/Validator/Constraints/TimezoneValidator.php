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

use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Timezones;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates whether a value is a valid timezone identifier.
 *
 * @author Javier Spagnoletti <phansys@gmail.com>
 * @author Hugo Hamon <hugohamon@neuf.fr>
 */
class TimezoneValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Timezone) {
            throw new UnexpectedTypeException($constraint, Timezone::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        if ($constraint->intlCompatible && 'Etc/Unknown' === \IntlTimeZone::createTimeZone($value)->getID()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Timezone::TIMEZONE_IDENTIFIER_INTL_ERROR)
                ->addViolation();

            return;
        }

        if (
            \in_array($value, self::getPhpTimezones($constraint->zone, $constraint->countryCode), true) ||
            \in_array($value, self::getIntlTimezones($constraint->zone, $constraint->countryCode), true)
        ) {
            return;
        }

        if ($constraint->countryCode) {
            $code = Timezone::TIMEZONE_IDENTIFIER_IN_COUNTRY_ERROR;
        } elseif (\DateTimeZone::ALL !== $constraint->zone) {
            $code = Timezone::TIMEZONE_IDENTIFIER_IN_ZONE_ERROR;
        } else {
            $code = Timezone::TIMEZONE_IDENTIFIER_ERROR;
        }

        $this->context->buildViolation($constraint->message)
              ->setParameter('{{ value }}', $this->formatValue($value))
              ->setCode($code)
              ->addViolation();
    }

    private static function getPhpTimezones(int $zone, string $countryCode = null): array
    {
        if (null !== $countryCode) {
            return @\DateTimeZone::listIdentifiers($zone, $countryCode) ?: [];
        }

        return \DateTimeZone::listIdentifiers($zone);
    }

    private static function getIntlTimezones(int $zone, string $countryCode = null): array
    {
        if (!class_exists(Timezones::class)) {
            return [];
        }

        if (null !== $countryCode) {
            try {
                return Timezones::forCountryCode($countryCode);
            } catch (MissingResourceException $e) {
                return [];
            }
        }

        $timezones = Timezones::getIds();

        if (\DateTimeZone::ALL === (\DateTimeZone::ALL & $zone)) {
            return $timezones;
        }

        $filtered = [];
        foreach ((new \ReflectionClass(\DateTimeZone::class))->getConstants() as $const => $flag) {
            if ($flag !== ($flag & $zone)) {
                continue;
            }

            $filtered[] = array_filter($timezones, static function ($id) use ($const) {
                return 0 === stripos($id, $const.'/');
            });
        }

        return $filtered ? array_merge(...$filtered) : [];
    }
}
