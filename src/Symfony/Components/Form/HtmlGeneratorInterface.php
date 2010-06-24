<?php

namespace Symfony\Components\Form;

/**
 * Marks classes able to generate HTML code
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface HtmlGeneratorInterface
{
    /**
     * Escapes a value for safe output in HTML
     *
     * Double escaping of already-escaped sequences is avoided by this method.
     *
     * @param  string $value  The unescaped or partially escaped value
     *
     * @return string  The fully escaped value
     */
    public function escape($value);

    /**
     * Generates the HTML code for a tag attribute
     *
     * @param string $name   The attribute name
     * @param string $value  The attribute value
     *
     * @return string  The HTML code of the attribute
     */
    public function attribute($name, $value);

    /**
     * Generates the HTML code for multiple tag attributes
     *
     * @param array $attributes  An array with attribute names as keys and
     *                           attribute values as elements
     *
     * @return string  The HTML code of the attribute list
     */
    public function attributes(array $attributes);

    /**
     * Generates the HTML code for a tag without content
     *
     * @param string $tag        The name of the tag
     * @param array $attributes  The attributes for the tag
     *
     * @return string  The HTML code for the tag
     */
    public function tag($tag, $attributes = array());

    /**
     * Generates the HTML code for a tag with content
     *
     * @param string $tag        The name of the tag
     * @param string $content    The content of the tag
     * @param array $attributes  The attributes for the tag
     *
     * @return string  The HTML code for the tag
     */
    public function contentTag($tag, $content, $attributes = array());
}
