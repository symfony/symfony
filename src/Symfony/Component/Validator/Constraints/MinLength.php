<?php

namespace Symfony\Component\Validator\Constraints;

class MinLength extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value is too short. It should have %limit% characters or more';
    public $limit;
    public $charset = 'UTF-8';

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