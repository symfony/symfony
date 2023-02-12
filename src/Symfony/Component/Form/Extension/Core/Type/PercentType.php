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
use Symfony\Component\OptionsResolver\OptionsResolver;

class PercentType extends AbstractType
{
    /**
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new PercentToLocalizedStringTransformer(
            $options['scale'],
            $options['type'],
            $options['rounding_mode'],
            $options['html5']
        ));
    }

    /**
     * @return void
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['symbol'] = $options['symbol'];

        if ($options['html5']) {
            $view->vars['type'] = 'number';
        }
    }

    /**
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'scale' => 0,
            'rounding_mode' => \NumberFormatter::ROUND_HALFUP,
            'symbol' => '%',
            'type' => 'fractional',
            'compound' => false,
            'html5' => false,
            'invalid_message' => 'Please enter a percentage value.',
        ]);

        $resolver->setAllowedValues('type', [
            'fractional',
            'integer',
        ]);
        $resolver->setAllowedValues('rounding_mode', [
            \NumberFormatter::ROUND_FLOOR,
            \NumberFormatter::ROUND_DOWN,
            \NumberFormatter::ROUND_HALFDOWN,
            \NumberFormatter::ROUND_HALFEVEN,
            \NumberFormatter::ROUND_HALFUP,
            \NumberFormatter::ROUND_UP,
            \NumberFormatter::ROUND_CEILING,
        ]);
        $resolver->setAllowedTypes('scale', 'int');
        $resolver->setAllowedTypes('symbol', ['bool', 'string']);
        $resolver->setAllowedTypes('html5', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'percent';
    }
}
