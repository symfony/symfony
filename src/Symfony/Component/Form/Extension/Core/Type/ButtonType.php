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

use Symfony\Component\Form\ButtonTypeInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A form button.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonType extends BaseType implements ButtonTypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars = array_merge($view->vars, array(
            'icon' => $options['icon'],
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        if (isset($options['icon']) && \is_array($options['icon'])) {
            if (!isset($options['icon']['name'])) {
                throw new InvalidOptionsException('The "icon" option must contain the key name.');
            }

            if (isset($options['icon']['space']) && !\is_numeric($options['icon']['space'])) {
                throw new InvalidOptionsException('The "space" option must be numeric.');
            }

            if (isset($options['icon']['align']) && !\in_array($options['icon']['align'], array('left', 'right'), true)) {
                throw new InvalidOptionsException('The "align" option must be one of the constants (left, right)');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'button';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'auto_initialize' => false,
            'icon' => null,
        ));

        $resolver->setAllowedTypes('icon', array('array', 'string', 'null'));
    }
}
