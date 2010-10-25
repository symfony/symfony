<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\InputField;

class TestInputField extends InputField
{
    /**
     * {@inheritDoc}
     */
    public function __construct($key, array $options = array())
    {
        $options['type'] = 'text';

        parent::__construct($key, $options);
    }
}