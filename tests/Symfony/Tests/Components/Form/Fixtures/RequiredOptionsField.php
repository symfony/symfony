<?php

namespace Symfony\Tests\Components\Form\Fixtures;

use Symfony\Components\Form\Field;

class RequiredOptionsField extends Field
{
    protected function configure()
    {
        $this->addOption('foo');
        $this->addRequiredOption('bar');
    }

    public function render(array $attributes = array())
    {
    }
}