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
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormView;

class PasswordType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('always_empty', $options['always_empty']);
    }

    public function buildView(FormView $view, FormInterface $form)
    {
        if ($form->getAttribute('always_empty') || !$form->isBound()) {
            $view->set('value', '');
        }
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'always_empty' => true,
        );
    }

    public function getParent(array $options)
    {
        return 'text';
    }

    public function getName()
    {
        return 'password';
    }
}
