<?php

namespace Symfony\Component\Validator\Constraints;

class Min extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value should be {{ limit }} or more';
    public $limit;

    /**
     * {@inheritDoc}
     */
    public function defaultOption()
    {
        return 'limit';
    }

    /**
     * {@inheritDoc}
     */
    public function requiredOptions()
    {
        return array('limit');
    }
}