<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Form\Util\PropertyPath;

/**
 * @author Julien Stoeffler <julien@nectalis.com>
 *
 * @api
 */
class DateOrderValidator extends ConstraintValidator
{

    public function validate($value, Constraint $constraint) {

        $earlierPropertyPath = new PropertyPath($constraint->earlierDatePropertyPath);
        $earlierDate = $earlierPropertyPath->getValue($value);

        $laterPropertyPath = new PropertyPath($constraint->laterDatePropertyPath);
        $laterDate = $laterPropertyPath->getValue($value);

        if(!$laterDate instanceof \DateTime || !$laterDate instanceof \DateTime)
            return;

        if($earlierDate->format('U') > $laterDate->format('U') || (!$constraint->allowEqualDates && $earlierDate->format('U') === $laterDate->format('U')))
            $this->context->addViolation($constraint->message);

    }
}
