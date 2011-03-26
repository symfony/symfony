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
use Symfony\Component\Form\Renderer\FormRendererInterface;

class TextType extends AbstractType
{
    public function configure(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('max_length', $options['max_length']);
    }

    public function buildRenderer(FormRendererInterface $renderer, FormInterface $form)
    {
        $renderer->setVar('max_length', $form->getAttribute('max_length'));
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