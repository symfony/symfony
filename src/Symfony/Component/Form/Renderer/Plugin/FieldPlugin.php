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
use Symfony\Component\Form\FieldInterface;

class FieldPlugin implements RendererPluginInterface
{
    /**
     * Renders the HTML enctype in the field tag, if necessary
     *
     * Example usage in Twig templates:
     *
     *     <field action="..." method="post" {{ field.render.enctype }}>
     *
     * @param Form $field   The field for which to render the encoding type
     */
    public function setUp(FieldInterface $field, RendererInterface $renderer)
    {
        $fieldKey = $field->getName();

        if ($field->hasParent()) {
            $parentRenderer = $field->getParent()->getRenderer();
            $parentId = $parentRenderer->getVar('id');
            $parentName = $parentRenderer->getVar('name');
            $id = sprintf('%s_%s', $parentId, $fieldKey);
            $name = sprintf('%s[%s]', $parentName, $fieldKey);
        } else {
            $id = $fieldKey;
            $name = $fieldKey;
        }

        $renderer->setVar('this', $renderer);
        $renderer->setVar('id', $id);
        $renderer->setVar('name', $name);
        $renderer->setVar('errors', $field->getErrors());
        $renderer->setVar('value', $field->getClientData());
        $renderer->setVar('disabled', $field->isDisabled());
        $renderer->setVar('required', $field->isRequired());
        $renderer->setVar('class', null);
        $renderer->setVar('max_length', null);
        $renderer->setVar('size', null);
        $renderer->setVar('label', ucfirst(strtolower(str_replace('_', ' ', $field->getName()))));
        $renderer->setVar('multipart', false);
    }
}