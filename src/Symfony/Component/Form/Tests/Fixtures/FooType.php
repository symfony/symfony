<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class FooType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setAttribute('foo', 'x');
        $builder->setAttribute('data_option', isset($options['data']) ? $options['data'] : null);
        // Important: array_key_exists(), not isset()
        // -> The "data" option is optional in FormType
        //    If it is given, the form's data will be locked to the value of the option
        //    Thus "data" must not be set in the array unless explicitely specified
        $builder->setAttribute('data_option_set', array_key_exists('data', $options));
    }

    public function getName()
    {
        return 'foo';
    }

    public function createBuilder($name, FormFactoryInterface $factory, array $options)
    {
        return new FormBuilder($name, null, new EventDispatcher(), $factory);
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'required' => false,
            'max_length' => null,
            'a_or_b' => 'a',
        ));

        $resolver->setOptional(array(
            'data',
        ));

        $resolver->setAllowedValues(array(
            'a_or_b' => array('a', 'b'),
        ));
    }

    public function getParent()
    {
        return null;
    }
}
