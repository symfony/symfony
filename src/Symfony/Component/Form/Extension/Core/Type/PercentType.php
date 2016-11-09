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
use Symfony\Component\Form\Extension\Core\DataTransformer\PercentToLocalizedStringTransformer;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class PercentType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new PercentToLocalizedStringTransformer($options['precision'], $options['type']));
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'precision' => 0,
            'type' => 'fractional',
            'compound' => false,
            'invalid_message' => 'Percent value provided is invalid.',
        ));

        $resolver->setAllowedValues(array(
            'type' => array(
                'fractional',
                'integer',
            ),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'percent';
    }
}
