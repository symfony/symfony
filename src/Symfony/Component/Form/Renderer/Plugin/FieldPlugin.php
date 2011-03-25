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

use Symfony\Component\Form\Renderer\FormRendererInterface;
use Symfony\Component\Form\FormInterface;

class FieldPlugin implements FormRendererPluginInterface
{
    /**
     * Renders the HTML enctype in the field tag, if necessary
     *
     * Example usage in Twig templates:
     *
     *     <field action="..." method="post" {{ field.render.enctype }}>
     *
     * @param Form $form   The field for which to render the encoding type
     */
    public function setUp(FormInterface $form, FormRendererInterface $renderer)
    {
        $fieldKey = $form->getName();

        if ($form->hasParent()) {
            $parentRenderer = $form->getParent()->getRenderer();
            $parentId = $parentRenderer->getVar('id');
            $parentName = $parentRenderer->getVar('name');
            $id = sprintf('%s_%s', $parentId, $fieldKey);
            $name = sprintf('%s[%s]', $parentName, $fieldKey);
        } else {
            $id = $fieldKey;
            $name = $fieldKey;
        }

        $renderer->setVar('renderer', $renderer);
        $renderer->setVar('id', $id);
        $renderer->setVar('name', $name);
        $renderer->setVar('errors', $form->getErrors());
        $renderer->setVar('value', $form->getClientData());
        $renderer->setVar('disabled', $form->isReadOnly());
        $renderer->setVar('required', $form->isRequired());
        $renderer->setVar('class', null);
        $renderer->setVar('max_length', null);
        $renderer->setVar('size', null);
        $renderer->setVar('label', ucfirst(strtolower(str_replace('_', ' ', $form->getName()))));
        $renderer->setVar('multipart', false);
        $renderer->setVar('attr', array());
    }
}