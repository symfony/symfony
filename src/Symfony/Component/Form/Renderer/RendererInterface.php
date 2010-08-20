<?php

namespace Symfony\Component\Form\Renderer;

use Symfony\Component\Form\HtmlGeneratorInterface;
use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Localizable;
use Symfony\Component\Form\Translatable;

/**
 * Renders a given form field.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface RendererInterface extends Localizable, Translatable
{
    /**
     * Sets the generator used for rendering the HTML
     *
     * @param HtmlGeneratorInterface $generator
     */
    public function setGenerator(HtmlGeneratorInterface $generator);

    /**
     * Returns the textual representation of the given field.
     *
     * @param  FieldInterface $field      The form field
     * @param  array $attributes          The attributes to include in the
     *                                    rendered output
     * @return string                     The rendered output
     * @throws InvalidArgumentException   If the $field is not instance of the
     *                                    expected class
     */
    public function render(FieldInterface $field, array $attributes = array());

    /**
     * Returns the textual representation of the errors of the given field.
     *
     * @param  FieldInterface $field      The form field
     * @return string                     The rendered output
     * @throws InvalidArgumentException   If the $field is not instance of the
     *                                    expected class
     */
    public function renderErrors(FieldInterface $field);
}