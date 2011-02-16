<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Renderer\Engine\EngineInterface;

class FieldRenderer implements RendererInterface
{
    private $field;

    private $engine;

    public function __construct(FieldInterface $field, EngineInterface $engine)
    {
        $this->field = $field;
        $this->engine = $engine;
    }

    protected function getField()
    {
        return $this->field;
    }

    protected function getEngine()
    {
        return $this->engine;
    }

    public function __toString()
    {
        return $this->widget();
    }

    /**
     * Renders the HTML enctype in the form tag, if necessary
     *
     * Example usage in Twig templates:
     *
     *     <form action="..." method="post" {{ form.render.enctype }}>
     *
     * @param Form $form   The form for which to render the encoding type
     */
    public function enctype()
    {
        return $this->field->isMultipart() ? 'enctype="multipart/form-data"' : '';
    }

    /**
     * Renders a field row.
     *
     * @param FieldInterface $field  The field to render as a row
     */
    public function row()
    {
        return $this->engine->render($this->field, 'field_row', array(
            'child'  => $this->field,
        ));
    }

    /**
     * Renders the HTML for an individual form field
     *
     * Example usage in Twig:
     *
     *     {{ form_field(field) }}
     *
     * You can pass attributes element during the call:
     *
     *     {{ form_field(field, {'class': 'foo'}) }}
     *
     * Some fields also accept additional variables as parameters:
     *
     *     {{ form_field(field, {}, {'separator': '+++++'}) }}
     *
     * @param FieldInterface $field      The field to render
     * @param array          $attributes HTML attributes passed to the template
     * @param array          $parameters Additional variables passed to the template
     * @param array|string   $resources  A resource or array of resources
     */
    public function widget(array $attributes = array(), array $parameters = array(), $resources = null)
    {
        if (null !== $resources && !is_array($resources)) {
            $resources = array($resources);
        }

        return $this->engine->render($this->field, 'field', array(
            'field'  => $this->field,
            'attr'   => $attributes,
            'params' => $parameters,
        ), $resources);
    }

    /**
     * Renders all hidden fields of the given field group
     *
     * @param FormInterface $group   The field group
     * @param array $params                Additional variables passed to the
     *                                     template
     */
    public function hidden(array $parameters = array())
    {
        return $this->engine->render($this->field, 'hidden', array(
            'field'  => $this->field,
            'params' => $parameters,
        ));
    }

    /**
     * Renders the errors of the given field
     *
     * @param FieldInterface $field  The field to render the errors for
     * @param array $params          Additional variables passed to the template
     */
    public function errors(array $parameters = array())
    {
        return $this->engine->render($this->field, 'errors', array(
            'field'  => $this->field,
            'params' => $parameters,
        ));
    }

    /**
     * Renders the label of the given field
     *
     * @param FieldInterface $field  The field to render the label for
     * @param array $params          Additional variables passed to the template
     */
    public function label($label = null, array $parameters = array())
    {
        return $this->render($this->field, 'label', array(
            'field'  => $this->field,
            'params' => $parameters,
            'label'  => null !== $label ? $label : ucfirst(strtolower(str_replace('_', ' ', $this->field->getKey()))),
        ));
    }
}