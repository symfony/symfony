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

use Symfony\Component\Form\Renderer\RendererInterface;
use Symfony\Component\Form\FormInterface;

class FormPlugin implements PluginInterface
{
    private $form;

    public function __construct(FormInterface $form)
    {
        $this->form = $form;
    }

    public function setUp(RendererInterface $renderer)
    {
        $fields = array();
        $visibleFields = array();

        foreach ($this->form as $key => $field) {
            $fields[$key] = $field->getRenderer();

            if (!$field->isHidden()) {
                $visibleFields[$key] = $field->getRenderer();
            }
        }

        $renderer->setVar('fields', $fields);
        $renderer->setVar('visible_fields', $visibleFields);
    }
}