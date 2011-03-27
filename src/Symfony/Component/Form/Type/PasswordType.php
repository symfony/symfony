<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Renderer\ThemeRendererInterface;

class PasswordType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('always_empty', $options['always_empty']);
    }

    public function buildRenderer(ThemeRendererInterface $renderer, FormInterface $form)
    {
        if ($form->getAttribute('always_empty') || !$form->isBound()) {
            $renderer->setVar('value', '');
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