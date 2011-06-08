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

    public function getAllowedOptionValues(array $options)
    {
        return array(
            'a_or_b' => array('c'),
        );
    }

    public function getExtendedType()
    {
        return 'foo';
    }
}
