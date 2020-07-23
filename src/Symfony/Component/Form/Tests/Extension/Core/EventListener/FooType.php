<?php

namespace Symfony\Component\Form\Tests\Extension\Core\EventListener;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class FooType.
 *
 * @author Patrick Bußmann <patrick.bussmann@bussmann-it.de>
 */
class FooType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('foo', TextType::class)
        ;
    }
}
