<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class FooType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('foo', 'x');
        $builder->setAttribute('data_option', $options['data']);
    }

    public function getName()
    {
        return 'foo';
    }

    public function createBuilder($name, FormFactoryInterface $factory, array $options)
    {
        return new FormBuilder($name, $factory, new EventDispatcher());
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data' => null,
            'required' => false,
            'max_length' => null,
            'a_or_b' => 'a',
        );
    }

    public function getAllowedOptionValues(array $options)
    {
        return array(
            'a_or_b' => array('a', 'b'),
        );
    }

    public function getParent(array $options)
    {
        return null;
    }
}
