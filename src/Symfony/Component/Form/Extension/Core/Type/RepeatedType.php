<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToDuplicatesTransformer;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RepeatedType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Overwrite required option for child fields
        $options['first_options']['required'] = $options['required'];
        $options['second_options']['required'] = $options['required'];

        if (!isset($options['options']['error_bubbling'])) {
            $options['options']['error_bubbling'] = $options['error_bubbling'];
        }

        $builder
            ->addViewTransformer(new ValueToDuplicatesTransformer(array(
                $options['first_name'],
                $options['second_name'],
            )))
            ->add($options['first_name'], $options['type'], array_merge($options['options'], $options['first_options']))
            ->add($options['second_name'], $options['type'], array_merge($options['options'], $options['second_options']))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'type' => 'text',
            'options' => array(),
            'first_options' => array(),
            'second_options' => array(),
            'first_name' => 'first',
            'second_name' => 'second',
            'error_bubbling' => false,
        ));

        $resolver->setAllowedTypes(array(
            'options' => 'array',
            'first_options' => 'array',
            'second_options' => 'array',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'repeated';
    }
}
