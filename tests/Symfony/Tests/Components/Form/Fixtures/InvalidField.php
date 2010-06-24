<?php

namespace Symfony\Tests\Components\Form\Fixtures;

use Symfony\Components\Form\Field;

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