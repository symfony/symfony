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
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
        $scale = function (Options $options) {
            if (null !== $options['precision']) {
                @trigger_error('The form option "precision" is deprecated since version 2.7 and will be removed in 3.0. Use "scale" instead.', E_USER_DEPRECATED);

                return $options['precision'];
            }

            return 0;
        };

        $resolver->setDefaults(array(
            // deprecated as of Symfony 2.7, to be removed in Symfony 3.0.
            'precision' => null,
            'scale' => $scale,
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
    public function getName()
    {
        return 'percent';
    }
}
