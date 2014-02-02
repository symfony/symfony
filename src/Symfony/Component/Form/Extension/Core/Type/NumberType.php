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
use Symfony\Component\Form\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class NumberType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new NumberToLocalizedStringTransformer(
            $options['precision'],
            $options['grouping'],
            $options['rounding_mode']
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            // default precision is locale specific (usually around 3)
            'precision'     => null,
            'grouping'      => false,
            'rounding_mode' => NumberToLocalizedStringTransformer::ROUND_HALF_UP,
            'compound'      => false,
        ));

        $resolver->setAllowedValues(array(
            'rounding_mode' => array(
                NumberToLocalizedStringTransformer::ROUND_FLOOR,
                NumberToLocalizedStringTransformer::ROUND_DOWN,
                NumberToLocalizedStringTransformer::ROUND_HALF_DOWN,
                NumberToLocalizedStringTransformer::ROUND_HALF_EVEN,
                NumberToLocalizedStringTransformer::ROUND_HALF_UP,
                NumberToLocalizedStringTransformer::ROUND_UP,
                NumberToLocalizedStringTransformer::ROUND_CEILING,
            ),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'number';
    }
}
