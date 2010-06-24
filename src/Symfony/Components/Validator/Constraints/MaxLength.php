<?php

namespace Symfony\Components\Validator\Constraints;

class MaxLength extends \Symfony\Components\Validator\Constraint
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