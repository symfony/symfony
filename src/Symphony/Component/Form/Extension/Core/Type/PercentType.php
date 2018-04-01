<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Core\Type;

use Symphony\Component\Form\AbstractType;
use Symphony\Component\Form\FormBuilderInterface;
use Symphony\Component\Form\Extension\Core\DataTransformer\PercentToLocalizedStringTransformer;
use Symphony\Component\OptionsResolver\OptionsResolver;

class PercentType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new PercentToLocalizedStringTransformer($options['scale'], $options['type']));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'scale' => 0,
            'type' => 'fractional',
            'compound' => false,
        ));

        $resolver->setAllowedValues('type', array(
            'fractional',
            'integer',
        ));

        $resolver->setAllowedTypes('scale', 'int');
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'percent';
    }
}
