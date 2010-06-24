<?php

namespace Symfony\Components\Validator\Constraints;

class Max extends \Symfony\Components\Validator\Constraint
{
    public $message = 'Symfony.Validator.Max.message';
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