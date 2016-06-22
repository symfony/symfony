<?php

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormBuilderInterface;

class AlternatingRowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $formFactory = $builder->getFormFactory();

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formFactory) {
            $form = $event->getForm();
            $type = $form->getName() % 2 === 0
                ? 'Symfony\Component\Form\Extension\Core\Type\TextType'
                : 'Symfony\Component\Form\Extension\Core\Type\TextareaType';
            $form->add('title', $type);
        });
    }
}
