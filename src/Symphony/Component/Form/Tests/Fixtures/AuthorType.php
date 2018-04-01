<?php

namespace Symphony\Component\Form\Tests\Fixtures;

use Symphony\Component\Form\AbstractType;
use Symphony\Component\Form\FormBuilderInterface;
use Symphony\Component\OptionsResolver\OptionsResolver;

class AuthorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('firstName')
            ->add('lastName')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Symphony\Component\Form\Tests\Fixtures\Author',
        ));
    }
}
