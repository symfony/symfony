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
use Symfony\Component\Form\Extension\Core\DataTransformer\TelToLocalizedStringTransformer;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Luis Cordova <cordoval@gmail.com>
 */
class TelType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new TelToLocalizedStringTransformer($options['format']));
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'format'      => 'spaced',
            'compound'  => false,
        ));

        $resolver->setAllowedValues(array(
            'format' => array(
                'spaced',
                'dotted',
            ),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'tel';
    }
}
