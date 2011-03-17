<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license infieldation, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Plugin;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Renderer\RendererInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class FormPlugin implements RendererPluginInterface
{
    public function setUp(FieldInterface $form, RendererInterface $renderer)
    {
        if (!$form instanceof FormInterface) {
            throw new UnexpectedTypeException($form, 'Symfony\Component\Form\FormInterface');
        }

        $fields = array();

        foreach ($this->form as $name => $field) {
            $fields[$name] = $field->getRenderer();
        }

        $renderer->setVar('fields', $fields);
    }
}