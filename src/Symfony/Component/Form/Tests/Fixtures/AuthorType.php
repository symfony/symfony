<?php

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class AuthorType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder
            ->add('firstName')
            ->add('lastName')
        ;
    }

    public function getName()
    {
        return 'author';
    }

    public function getDefaultOptions()
    {
        return array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
        );
    }
}
