<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilder;

class FooTypeBarExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('bar', 'x');
    }

    public function getExtendedType()
    {
        return 'foo';
    }
}