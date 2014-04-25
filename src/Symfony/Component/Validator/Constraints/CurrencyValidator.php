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

use Symfony\Component\Intl\Intl;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates whether a value is a valid currency
 *
 * @author Miha Vrhovnik <miha.vrhovnik@pagein.si>
 *
 * @api
 */
class CurrencyValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Currency) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Currency');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;
        $currencies = Intl::getCurrencyBundle()->getCurrencyNames();

        if (!isset($currencies[$value])) {
            $this->context->addViolation($constraint->message, array('{{ value }}' => $value));
        }
    }
}
