<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\Field;
use Symfony\Component\Form\ValueTransformer\ValueTransformerInterface;

class TestField extends Field
{
    public function render(array $attributes = array())
    {
    }

    /**
     * Expose method for testing purposes
     */
    public function getNormalizedData()
    {
        return parent::getNormalizedData();
    }
}