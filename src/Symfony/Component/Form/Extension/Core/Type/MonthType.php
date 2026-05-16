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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MonthType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['type'] = 'month';

        if (null !== $options['min']) {
            $view->vars['attr']['min'] = $options['min'];
        }

        if (null !== $options['max']) {
            $view->vars['attr']['max'] = $options['max'];
        }

        if (null !== $options['step']) {
            $view->vars['attr']['step'] = $options['step'];
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'min' => null,
            'max' => null,
            'step' => null,
        ]);

        $resolver->setAllowedTypes('min', ['null', 'string']);
        $resolver->setAllowedTypes('max', ['null', 'string']);
        $resolver->setAllowedTypes('step', ['null', 'int', 'string']);
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'month';
    }
}
