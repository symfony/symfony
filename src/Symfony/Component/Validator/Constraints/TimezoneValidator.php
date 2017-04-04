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
        $timezoneIds = \DateTimeZone::listIdentifiers($constraint->timezone, $constraint->countryCode);

        if ($timezoneIds && !in_array($value, $timezoneIds, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ extra_info }}', $this->formatExtraInfo($constraint->timezone, $constraint->countryCode))
                ->setCode(Timezone::NO_SUCH_TIMEZONE_ERROR)
                ->addViolation();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'timezone';
    }

    /**
     * Format the extra info which is appended to validation message based on
     * constraint options
     *
     * @param int $timezone
     * @param string|null $countryCode
     *
     * @return string
     */
    protected function formatExtraInfo($timezone, $countryCode = null)
    {
        if (!$timezone) {
            return '';
        }
        $r = new \ReflectionClass('\DateTimeZone');
        $consts = $r->getConstants();

        if (!$value = array_search($timezone, $consts, true)) {
            $value = $timezone;
        }

        $value = ' for "'.$value.'" zone';

        if ($countryCode) {
            $value = ' for ISO 3166-1 country code '.$countryCode;
        }

        return $this->formatValue($value);
    }
}
