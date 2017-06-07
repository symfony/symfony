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

/**
 * A range form element.
 *
 * @author Carlos Revillo <crevillo@gmail.com>
 * @author Pawel Krynicki <pawel.krynicki@hotmail.com>
 */
class RangeType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['attr']['min'] = $options['min'];
        $view->vars['attr']['max'] = $options['max'];

        if (isset($options['step'])) {
            $view->vars['attr']['step'] = $options['step'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(array('min', 'max'));

        $resolver->setDefined('step');

        $resolver->setAllowedTypes('min', 'numeric');
        $resolver->setAllowedTypes('max', 'numeric');
        $resolver->setAllowedTypes('step', 'numeric');

        $resolver->setAllowedValues('step', function ($value) {
            return $value > 0;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'number';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'range';
    }
}
