<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\ToggleField;

class TestToggleField extends ToggleField
{
    /**
     * {@inheritDoc}
     */
    public function __construct($key, array $options = array())
    {
        $options['type'] = 'checkbox';

        parent::__construct($key, $options);
    }
}