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
use Symfony\Component\Form\Extension\Core\DataTransformer\PercentToLocalizedStringTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PercentType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new PercentToLocalizedStringTransformer(
            $options['scale'],
            $options['type'],
            $options['rounding_mode'],
            false
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['symbol'] = $options['symbol'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'scale' => 0,
            'rounding_mode' => function (Options $options) {
                trigger_deprecation('symfony/form', '5.1', sprintf('Not configuring the "rounding_mode" option is deprecated. It will default to "%s::ROUND_HALF_UP" in Symfony 6.0.', PercentToLocalizedStringTransformer::class));

                return null;
            },
            'symbol' => '%',
            'type' => 'fractional',
            'compound' => false,
        ]);

        $resolver->setAllowedValues('type', [
            'fractional',
            'integer',
        ]);
        $resolver->setAllowedValues('rounding_mode', [
            null,
            PercentToLocalizedStringTransformer::ROUND_FLOOR,
            PercentToLocalizedStringTransformer::ROUND_DOWN,
            PercentToLocalizedStringTransformer::ROUND_HALF_DOWN,
            PercentToLocalizedStringTransformer::ROUND_HALF_EVEN,
            PercentToLocalizedStringTransformer::ROUND_HALF_UP,
            PercentToLocalizedStringTransformer::ROUND_UP,
            PercentToLocalizedStringTransformer::ROUND_CEILING,
        ]);
        $resolver->setAllowedTypes('scale', 'int');
        $resolver->setAllowedTypes('symbol', ['bool', 'string']);
        $resolver->setDeprecated('rounding_mode', 'symfony/form', '5.1', function (Options $options, $roundingMode) {
            if (null === $roundingMode) {
                return sprintf('Not configuring the "rounding_mode" option is deprecated. It will default to "%s::ROUND_HALF_UP" in Symfony 6.0.', PercentToLocalizedStringTransformer::class);
            }

            return '';
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'percent';
    }
}
