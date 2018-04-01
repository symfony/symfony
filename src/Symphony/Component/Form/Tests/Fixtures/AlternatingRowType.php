<?php

namespace Symphony\Component\Form\Tests\Fixtures;

use Symphony\Component\Form\AbstractType;
use Symphony\Component\Form\FormEvents;
use Symphony\Component\Form\FormEvent;
use Symphony\Component\Form\FormBuilderInterface;

class AlternatingRowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $formFactory = $builder->getFormFactory();

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formFactory) {
            $form = $event->getForm();
            $type = 0 === $form->getName() % 2
                ? 'Symphony\Component\Form\Extension\Core\Type\TextType'
                : 'Symphony\Component\Form\Extension\Core\Type\TextareaType';
            $form->add('title', $type);
        });
    }
}
