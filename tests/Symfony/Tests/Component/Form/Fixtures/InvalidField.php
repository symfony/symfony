<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\Field;

class InvalidField extends Field
{
    public function isValid()
    {
        return false;
    }

    public function render(array $attributes = array())
    {
    }
}