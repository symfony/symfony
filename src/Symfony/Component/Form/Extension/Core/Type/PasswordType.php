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
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class PasswordType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['reset_on_submit'] || !$form->isSubmitted()) {
            $view->vars['value'] = '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        // BC with old "always_empty" option
        $resetOnSubmit = function (Options $options) {
            if (null !== $options['always_empty']) {
                // Uncomment this as soon as the deprecation note should be shown
                // trigger_error('The form option "always_empty" is deprecated since version 2.3 and will be removed in 3.0. Use "reset_on_submit" instead.', E_USER_DEPRECATED);
                return $options['always_empty'];
            }

            return true;
        };

        $resolver->setDefaults(array(
            'reset_on_submit' => $resetOnSubmit,
            'always_empty'    => null,
            'trim'            => false,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'text';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'password';
    }
}
