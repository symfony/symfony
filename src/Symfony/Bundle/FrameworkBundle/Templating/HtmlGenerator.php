<?php

namespace Symfony\Bundle\FrameworkBundle\Templating;

/**
 * An implementation of HtmlGeneratorInterface
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HtmlGenerator implements HtmlGeneratorInterface
{
    /**
     * Whether to produce XHTML compliant code
     * @var boolean
     */
    protected static $xhtml   = true;

    /**
     * The charset used during generating
     * @var string
     */
    protected $charset;

    /**
     * Sets the charset used for rendering
     *
     * @param string $charset
     */
    public function __construct($charset = 'UTF-8')
    {
        $this->charset = $charset;
    }

    /**
     * Sets the XHTML generation flag.
     *
     * @param bool $boolean  true if renderers must be generated as XHTML, false otherwise
     */
    static public function setXhtml($boolean)
    {
        self::$xhtml = (boolean) $boolean;
    }

    /**
     * Returns whether to generate XHTML tags or not.
     *
     * @return bool true if renderers must be generated as XHTML, false otherwise
     */
    static public function isXhtml()
    {
        return self::$xhtml;
    }

    /**
     * {@inheritDoc}
     */
    public function tag($tag, $attributes = array())
    {
        if (empty($tag)) {
            return '';
        }

        return sprintf('<%s%s%s', $tag, $this->attributes($attributes), self::$xhtml ? ' />' : (strtolower($tag) == 'input' ? '>' : sprintf('></%s>', $tag)));
    }

    /**
     * {@inheritDoc}
     */
    public function contentTag($tag, $content = null, $attributes = array())
    {
        if (empty($tag)) {
            return '';
        }

        return sprintf('<%s%s>%s</%s>', $tag, $this->attributes($attributes), $content, $tag);
    }

    /**
     * {@inheritDoc}
     */
    public function attribute($name, $value)
    {
        if (true === $value) {
            return self::$xhtml ? sprintf('%s="%s"', $name, $this->escape($name)) : $this->escape($name);
        } else {
            return sprintf('%s="%s"', $name, $this->escape($value));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function attributes(array $attributes)
    {
        return implode('', array_map(array($this, 'attributesCallback'), array_keys($attributes), array_values($attributes)));
    }

    public function choices(array $preferredChoices, array $choices, $empty, array $selected)
    {
        $html = '';

        if (false !== $empty) {
            $html .= $this->doChoices(array('' => $empty), $selected)."\n";
        }

        if (count($preferredChoices) > 0) {
            $html .= $this->doChoices($preferredChoices, $selected)."\n";
            $html .= $this->contentTag('option', $origin->getOption('separator'), array('disabled' => true))."\n";
        }

        $html .= $this->doChoices($choices, $selected)."\n";

        return $html;
    }

    protected function doChoices(array $choices, array $selected)
    {
        $options = array();
        foreach ($choices as $key => $option) {
            if (is_array($option)) {
                $options[] = $this->contentTag(
                    'optgroup',
                    "\n".renderChoices($option, $selected)."\n",
                    array('label' => $this->escape($key))
                );
            } else {
                $attributes = array('value' => $this->escape($key));

                if (isset($selected[strval($key)])) {
                    $attributes['selected'] = true;
                }

                $options[] = $this->contentTag(
                    'option',
                    $this->escape($option),
                    $attributes
                );
            }
        }

        return implode("\n", $options);
    }

    /**
     * Prepares an attribute key and value for HTML representation.
     *
     * It removes empty attributes, except for the value one.
     *
     * @param  string $name   The attribute name
     * @param  string $value  The attribute value
     *
     * @return string The HTML representation of the HTML key attribute pair.
     */
    private function attributesCallback($name, $value)
    {
        if (false === $value || null === $value || ('' === $value && 'value' != $name)) {
            return '';
        } else {
            return ' '.$this->attribute($name, $value);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, $this->charset, false);
    }
}
