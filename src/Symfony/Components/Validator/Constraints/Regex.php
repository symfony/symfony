<?php

namespace Symfony\Components\Validator\Constraints;

class Regex extends \Symfony\Components\Validator\Constraint
{
    public $message = 'Symfony.Validator.Regex.message';
    public $pattern;
    public $match = true;

    /**
     * {@inheritDoc}
     */
    public function defaultOption()
    {
        return 'pattern';
    }

    /**
     * {@inheritDoc}
     */
    public function requiredOptions()
    {
        return array('pattern');
    }
}