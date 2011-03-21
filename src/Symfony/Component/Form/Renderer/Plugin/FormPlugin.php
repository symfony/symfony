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

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Renderer\FormRendererInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class FormPlugin implements FormRendererPluginInterface
{
    public function setUp(FormInterface $form, FormRendererInterface $renderer)
    {
        if (!$form instanceof FormInterface) {
            throw new UnexpectedTypeException($form, 'Symfony\Component\Form\FormInterface');
        }

        $fields = array();
        $multipart = false;

        foreach ($form as $name => $field) {
            $fields[$name] = $field->getRenderer();
            $multipart = $multipart || $fields[$name]->getVar('multipart');
        }

        $renderer->setVar('fields', $fields);
        $renderer->setVar('multipart', $multipart);
    }
}