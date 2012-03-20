<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class TestTranslationType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->add('field1', 'choice', array(
            'choices' => array('1' => 'choice.1', '2' => 'choice.2'),
            'required' => true,
            'expanded' => true,
            'translation_domain' => 'field1'
        ));
        $builder->add('field2', 'checkbox', array('required' => true));
        $builder->add('field3', 'text');
    }

    public function getDefaultOptions(array $options)
    {
        return array('translation_domain' => 'test');
    }

    public function getName()
    {
        return 'test_translation_type';
    }
}
