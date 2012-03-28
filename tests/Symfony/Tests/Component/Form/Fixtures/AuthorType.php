<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\FormInterface;

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

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Symfony\Tests\Component\Form\Fixtures\Author',
        );
    }
}
