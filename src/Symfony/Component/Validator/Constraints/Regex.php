<?php

namespace Symfony\Component\Validator\Constraints;

class Regex extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value is not valid';
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