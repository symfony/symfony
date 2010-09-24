<?php

namespace Symfony\Component\Validator\Constraints;

class MaxLength extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value is too long. It should have %limit% characters or less';
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