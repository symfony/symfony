<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler;

use Symfony\Component\DomCrawler\Field\FormField;

/**
 * Form represents an HTML form.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Form extends Link implements \ArrayAccess
{
    /**
     * @var \DOMNode
     */
    private $button;
    /**
     * @var Field\FormField[]
     */
    private $fields;

    /**
     * Constructor.
     *
     * @param \DOMNode $node       A \DOMNode instance
     * @param string   $currentUri The URI of the page where the form is embedded
     * @param string   $method     The method to use for the link (if null, it defaults to the method defined by the form)
     *
     * @throws \LogicException if the node is not a button inside a form tag
     *
     * @api
     */
    public function __construct(\DOMNode $node, $currentUri, $method = null)
    {
        parent::__construct($node, $currentUri, $method);

        $this->initialize();
    }

    /**
     * Gets the form node associated with this form.
     *
     * @return \DOMNode A \DOMNode instance
     */
    public function getFormNode()
    {
        return $this->node;
    }

    /**
     * Sets the value of the fields.
     *
     * @param array $values An array of field values
     *
     * @return Form
     *
     * @api
     */
    public function setValues(array $values)
    {
        foreach ($values as $name => $value) {
            $this[$name] = $value;
        }

        return $this;
    }

    /**
     * Gets the field values.
     *
     * The returned array does not include file fields (@see getFiles).
     *
     * @return array An array of field values.
     *
     * @api
     */
    public function getValues()
    {
        $values = array();
        foreach ($this->fields as $name => $field) {
            if ($field->isDisabled()) {
                continue;
            }

            if (!$field instanceof Field\FileFormField && $field->hasValue()) {
                $values[$name] = $field->getValue();
            }
        }

        return $values;
    }

    /**
     * Gets the file field values.
     *
     * @return array An array of file field values.
     *
     * @api
     */
    public function getFiles()
    {
        if (!in_array($this->getMethod(), array('POST', 'PUT', 'DELETE'))) {
            return array();
        }

        $files = array();
        foreach ($this->fields as $name => $field) {
            if ($field->isDisabled()) {
                continue;
            }

            if ($field instanceof Field\FileFormField) {
                $files[$name] = $field->getValue();
            }
        }

        return $files;
    }

    /**
     * Gets the field values as PHP.
     *
     * This method converts fields with th array notation
     * (like foo[bar] to arrays) like PHP does.
     *
     * @return array An array of field values.
     *
     * @api
     */
    public function getPhpValues()
    {
        $qs = http_build_query($this->getValues(), '', '&');
        parse_str($qs, $values);

        return $values;
    }

    /**
     * Gets the file field values as PHP.
     *
     * This method converts fields with th array notation
     * (like foo[bar] to arrays) like PHP does.
     *
     * @return array An array of field values.
     *
     * @api
     */
    public function getPhpFiles()
    {
        $qs = http_build_query($this->getFiles(), '', '&');
        parse_str($qs, $values);

        return $values;
    }

    /**
     * Gets the URI of the form.
     *
     * The returned URI is not the same as the form "action" attribute.
     * This method merges the value if the method is GET to mimics
     * browser behavior.
     *
     * @return string The URI
     *
     * @api
     */
    public function getUri()
    {
        $uri = parent::getUri();

        if (!in_array($this->getMethod(), array('POST', 'PUT', 'DELETE')) && $queryString = http_build_query($this->getValues(), null, '&')) {
            $sep = false === strpos($uri, '?') ? '?' : '&';
            $uri .= $sep.$queryString;
        }

        return $uri;
    }

    protected function getRawUri()
    {
        return $this->node->getAttribute('action');
    }

    /**
     * Gets the form method.
     *
     * If no method is defined in the form, GET is returned.
     *
     * @return string The method
     *
     * @api
     */
    public function getMethod()
    {
        if (null !== $this->method) {
            return $this->method;
        }

        return $this->node->getAttribute('method') ? strtoupper($this->node->getAttribute('method')) : 'GET';
    }

    /**
     * Returns true if the named field exists.
     *
     * @param string $name The field name
     *
     * @return Boolean true if the field exists, false otherwise
     *
     * @api
     */
    public function has($name)
    {
        return isset($this->fields[$name]);
    }

    /**
     * Removes a field from the form.
     *
     * @param string $name The field name
     *
     * @api
     */
    public function remove($name)
    {
        unset($this->fields[$name]);
    }

    /**
     * Gets a named field.
     *
     * @param string $name The field name
     *
     * @return FormField The field instance
     *
     * @throws \InvalidArgumentException When field is not present in this form
     *
     * @api
     */
    public function get($name)
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(sprintf('The form has no "%s" field', $name));
        }

        return $this->fields[$name];
    }

    /**
     * Sets a named field.
     *
     * @param Field\FormField $field The field
     *
     * @api
     */
    public function set(Field\FormField $field)
    {
        $this->fields[$field->getName()] = $field;
    }

    /**
     * Gets all fields.
     *
     * @return array An array of fields
     *
     * @api
     */
    public function all()
    {
        return $this->fields;
    }

    private function initialize()
    {
        $this->fields = array();

        $document = new \DOMDocument('1.0', 'UTF-8');
        $node = $document->importNode($this->node, true);
        $button = $document->importNode($this->button, true);
        $root = $document->appendChild($document->createElement('_root'));
        $root->appendChild($node);
        $root->appendChild($button);
        $xpath = new \DOMXPath($document);

        foreach ($xpath->query('descendant::input | descendant::button | descendant::textarea | descendant::select', $root) as $node) {
            if (!$node->hasAttribute('name') || !$node->getAttribute('name')) {
                continue;
            }

            $nodeName = $node->nodeName;

            if ($node === $button) {
                $this->set(new Field\InputFormField($node));
            } elseif ('select' == $nodeName || 'input' == $nodeName && 'checkbox' == $node->getAttribute('type')) {
                $this->set(new Field\ChoiceFormField($node));
            } elseif ('input' == $nodeName && 'radio' == $node->getAttribute('type')) {
                if ($this->has($node->getAttribute('name'))) {
                    $this->get($node->getAttribute('name'))->addChoice($node);
                } else {
                    $this->set(new Field\ChoiceFormField($node));
                }
            } elseif ('input' == $nodeName && 'file' == $node->getAttribute('type')) {
                $this->set(new Field\FileFormField($node));
            } elseif ('input' == $nodeName && !in_array($node->getAttribute('type'), array('submit', 'button', 'image'))) {
                $this->set(new Field\InputFormField($node));
            } elseif ('textarea' == $nodeName) {
                $this->set(new Field\TextareaFormField($node));
            }
        }
    }

    /**
     * Returns true if the named field exists.
     *
     * @param string $name The field name
     *
     * @return Boolean true if the field exists, false otherwise
     */
    public function offsetExists($name)
    {
        return $this->has($name);
    }

    /**
     * Gets the value of a field.
     *
     * @param string $name The field name
     *
     * @return FormField The associated Field instance
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetGet($name)
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(sprintf('The form field "%s" does not exist', $name));
        }

        return $this->fields[$name];
    }

    /**
     * Sets the value of a field.
     *
     * @param string       $name  The field name
     * @param string|array $value The value of the field
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetSet($name, $value)
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(sprintf('The form field "%s" does not exist', $name));
        }

        $this->fields[$name]->setValue($value);
    }

    /**
     * Removes a field from the form.
     *
     * @param string $name The field name
     */
    public function offsetUnset($name)
    {
        $this->remove($name);
    }

    protected function setNode(\DOMNode $node)
    {
        $this->button = $node;
        if ('button' == $node->nodeName || ('input' == $node->nodeName && in_array($node->getAttribute('type'), array('submit', 'button', 'image')))) {
            do {
                // use the ancestor form element
                if (null === $node = $node->parentNode) {
                    throw new \LogicException('The selected node does not have a form ancestor.');
                }
            } while ('form' != $node->nodeName);
        } else {
            throw new \LogicException(sprintf('Unable to submit on a "%s" tag.', $node->nodeName));
        }

        $this->node = $node;
    }
}
