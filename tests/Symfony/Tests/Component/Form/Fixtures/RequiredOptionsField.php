<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\Field;

class RequiredOptionsField extends Field
{
    protected function configure()
    {
        $this->addOption('foo');
        $this->addRequiredOption('bar');

        parent::configure();
    }

    public function render(array $attributes = array())
    {
    }
}