<?php

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class AlternatingRowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $type = 0 === $form->getName() % 2
                ? 'Symfony\Component\Form\Extension\Core\Type\TextType'
                : 'Symfony\Component\Form\Extension\Core\Type\TextareaType';
            $form->add('title', $type);
        });
    }
}
