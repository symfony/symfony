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
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NumberType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new NumberToLocalizedStringTransformer(
            $options['scale'],
            $options['grouping'],
            $options['rounding_mode']
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $scale = function (Options $options) {
            if (null !== $options['precision']) {
                @trigger_error('The form option "precision" is deprecated since version 2.7 and will be removed in 3.0. Use "scale" instead.', E_USER_DEPRECATED);
            }

            return $options['precision'];
        };

        $resolver->setDefaults(array(
            // deprecated as of Symfony 2.7, to be removed in Symfony 3.0
            'precision' => null,
            // default scale is locale specific (usually around 3)
            'scale' => $scale,
            'grouping' => false,
            'rounding_mode' => NumberToLocalizedStringTransformer::ROUND_HALF_UP,
            'compound' => false,
        ));

        $resolver->setAllowedValues('rounding_mode', array(
            NumberToLocalizedStringTransformer::ROUND_FLOOR,
            NumberToLocalizedStringTransformer::ROUND_DOWN,
            NumberToLocalizedStringTransformer::ROUND_HALF_DOWN,
            NumberToLocalizedStringTransformer::ROUND_HALF_EVEN,
            NumberToLocalizedStringTransformer::ROUND_HALF_UP,
            NumberToLocalizedStringTransformer::ROUND_UP,
            NumberToLocalizedStringTransformer::ROUND_CEILING,
        ));

        $resolver->setAllowedTypes('scale', array('null', 'int'));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'number';
    }
}
