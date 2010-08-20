<?php

namespace Symfony\Component\Validator\Constraints;

class Max extends \Symfony\Component\Validator\Constraint
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