<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 *
 * @api
 */
class DateOrder extends Constraint
{
    public $message = 'Please check the dates.';
    public $earlierDatePropertyPath = null;
    public $laterDatePropertyPath = null;
    public $allowEqualDates = false;

    /**
     * {@inheritDoc}
     */
    public function getTargets() {
        return self::CLASS_CONSTRAINT;
    }


}
