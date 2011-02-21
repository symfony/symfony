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

class PasswordValuePlugin implements PluginInterface
{
    private $field;

    private $alwaysEmpty;

    public function __construct(FieldInterface $field, $alwaysEmpty = true)
    {
        $this->field = $field;
        $this->alwaysEmpty = $alwaysEmpty;
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
        $value = $this->alwaysEmpty || !$this->field->isSubmitted()
                ? ''
                : $this->field->getDisplayedData();

        $renderer->setVar('value', $value);
    }
}