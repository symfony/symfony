<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Constraints;

use Symphony\Component\Intl\Intl;
use Symphony\Component\Validator\Constraint;
use Symphony\Component\Validator\ConstraintValidator;
use Symphony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates whether a value is a valid country code.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CountryValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Country) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Country');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;
        $countries = Intl::getRegionBundle()->getCountryNames();

        if (!isset($countries[$value])) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Country::NO_SUCH_COUNTRY_ERROR)
                ->addViolation();
        }
    }
}
