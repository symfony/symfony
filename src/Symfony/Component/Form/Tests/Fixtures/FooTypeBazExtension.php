<?php

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilder;

class FooTypeBazExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('baz', 'x');
    }

    public function getExtendedType()
    {
        return 'foo';
    }
}
