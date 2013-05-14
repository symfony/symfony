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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class DateValidator extends ConstraintValidator
{
    const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})$/';

    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if($value instanceof \DateTime) {
            $dateTime = $value;
        } else {

            if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
                throw new UnexpectedTypeException($value, 'string');
            }

            $value = (string) $value;

            if (!preg_match(static::PATTERN, $value, $matches) || !checkdate($matches[2], $matches[3], $matches[1])) {
                $this->context->addViolation($constraint->message, array('{{ value }}' => $value));

                return;
            }

            $dateTime = new \DateTime($matches[1].'-'.$matches[2].'-'.$matches[3]);
        }

        if (null !== $constraint->before && $dateTime >= $constraint->before) {
            $formattedBeforeDate = $constraint->dateFormatter->format($constraint->before);
            $this->context->addViolation($constraint->messageBeforeDate, array('{{ value }}' => $value, '{{ before }}' => $formattedBeforeDate));

            return;
        }

        if (null !== $constraint->after && $dateTime <= $constraint->after) {
            $formattedAfterDate = $constraint->dateFormatter->format($constraint->after);
            $this->context->addViolation($constraint->messageAfterDate, array('{{ value }}' => $value, '{{ after }}' => $formattedAfterDate));

            return;
        }
    }
}
