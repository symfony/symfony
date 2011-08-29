<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Field;

/**
 * FormField is the abstract class for all form fields.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class FormField
{
    protected $node;
    protected $name;
    protected $value;
    protected $document;
    protected $xpath;
    protected $disabled;

    /**
     * Constructor.
     *
     * @param \DOMNode $node The node associated with this field
     */
    public function __construct(\DOMNode $node)
    {
        $this->node = $node;
        $this->name = $node->getAttribute('name');

        $this->document = new \DOMDocument('1.0', 'UTF-8');
        $this->node = $this->document->importNode($this->node, true);

        $root = $this->document->appendChild($this->document->createElement('_root'));
        $root->appendChild($this->node);
        $this->xpath = new \DOMXPath($this->document);

        $this->initialize();
    }

    /**
     * Returns the name of the field.
     *
     * @return string The name of the field
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the value of the field.
     *
     * @return string|array The value of the field
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Sets the value of the field.
     *
     * @param string $value The value of the field
     *
     * @api
     */
    public function setValue($value)
    {
        $this->value = (string) $value;
    }

    /**
     * Returns true if the field should be included in the submitted values.
     *
     * @return Boolean true if the field should be included in the submitted values, false otherwise
     */
    public function hasValue()
    {
        return true;
    }

    public function isDisabled()
    {
        return $this->node->hasAttribute('disabled');
    }

    /**
     * Initializes the form field.
     */
    abstract protected function initialize();
}
