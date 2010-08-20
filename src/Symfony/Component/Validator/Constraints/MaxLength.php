<?php

namespace Symfony\Component\Validator\Constraints;

class MaxLength extends \Symfony\Component\Validator\Constraint
{
    public $message = 'Symfony.Validator.MaxLength.message';
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