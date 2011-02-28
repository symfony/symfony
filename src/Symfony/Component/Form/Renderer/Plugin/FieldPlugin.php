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

class FieldPlugin implements PluginInterface
{
    private $field;

    public function __construct(FieldInterface $field)
    {
        $this->field = $field;
    }

    /**
     * Renders the HTML enctype in the field tag, if necessary
     *
     * Example usage in Twig templates:
     *
     *     <field action="..." method="post" {{ field.render.enctype }}>
     *
     * @param Form $field   The field for which to render the encoding type
     */
    public function setUp(RendererInterface $renderer)
    {
        $fieldKey = $this->field->getKey();

        if ($this->field->hasParent()) {
            $parentRenderer = $this->field->getParent()->getRenderer();
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
        $renderer->setVar('errors', $this->field->getErrors());
        $renderer->setVar('value', $this->field->getDisplayedData());
        $renderer->setVar('disabled', $this->field->isDisabled());
        $renderer->setVar('required', $this->field->isRequired());
        $renderer->setVar('class', null);
        $renderer->setVar('max_length', null);
        $renderer->setVar('size', null);
        $renderer->setVar('label', ucfirst(strtolower(str_replace('_', ' ', $this->field->getKey()))));
    }
}