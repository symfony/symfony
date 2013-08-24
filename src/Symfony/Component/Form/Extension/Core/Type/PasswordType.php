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
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @since v2.0.0
 */
class PasswordType extends AbstractType
{
    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['always_empty'] || !$form->isSubmitted()) {
            $view->vars['value'] = '';
        }
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'always_empty' => true,
            'trim'         => false,
        ));
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getParent()
    {
        return 'text';
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function getName()
    {
        return 'password';
    }
}
