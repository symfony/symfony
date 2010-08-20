<?php

namespace Symfony\Component\Validator\Constraints;

class Min extends \Symfony\Component\Validator\Constraint
{
    public $message = 'Symfony.Validator.Min.message';
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