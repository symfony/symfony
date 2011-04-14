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

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\TemplateContext;

class TextType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('max_length', $options['max_length']);
    }

    public function buildContext(TemplateContext $context, FormInterface $form)
    {
        $context->setVar('max_length', $form->getAttribute('max_length'));
    }

    public function getParent(array $options)
    {
        return 'field';
    }

    public function getName()
    {
        return 'text';
    }
}