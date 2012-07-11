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
            $this->fields->set($name, $value);
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
        foreach ($this->fields->all() as $name => $field) {
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
        if (!in_array($this->getMethod(), array('POST', 'PUT', 'DELETE', 'PATCH'))) {
            return array();
        }

        $files = array();

        foreach ($this->fields->all() as $name => $field) {
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
     * This method converts fields with the array notation
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
     * This method converts fields with the array notation
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

        if (!in_array($this->getMethod(), array('POST', 'PUT', 'DELETE', 'PATCH')) && $queryString = http_build_query($this->getValues(), null, '&')) {
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
        return $this->fields->has($name);
    }

    /**
     * Removes a field from the form.
     *
     * @param string $name The field name
     *
     * @throws \InvalidArgumentException when the name is malformed
     *
     * @api
     */
    public function remove($name)
    {
        $this->fields->remove($name);
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
        return $this->fields->get($name);
    }

    /**
     * Sets a named field.
     *
     * @param FormField $field The field
     *
     * @api
     */
    public function set(FormField $field)
    {
        $this->fields->add($field);
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
        return $this->fields->all();
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
        return $this->fields->get($name);
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
        $this->fields->set($name, $value);
    }

    /**
     * Removes a field from the form.
     *
     * @param string $name The field name
     */
    public function offsetUnset($name)
    {
        $this->fields->remove($name);
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
        } elseif ('form' != $node->nodeName) {
            throw new \LogicException(sprintf('Unable to submit on a "%s" tag.', $node->nodeName));
        }

        $this->node = $node;
    }

    private function initialize()
    {
        $this->fields = new FormFieldRegistry();

        $document = new \DOMDocument('1.0', 'UTF-8');
        $node = $document->importNode($this->node, true);
        $button = $document->importNode($this->button, true);
        $root = $document->appendChild($document->createElement('_root'));
        $root->appendChild($node);
        $root->appendChild($button);
        $xpath = new \DOMXPath($document);

        foreach ($xpath->query('descendant::input | descendant::textarea | descendant::select', $root) as $node) {
            if (!$node->hasAttribute('name')) {
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
}

class FormFieldRegistry
{
    private $fields = array();

    private $base;

    /**
     * Adds a field to the registry.
     *
     * @param FormField $field The field
     *
     * @throws \InvalidArgumentException when the name is malformed
     */
    public function add(FormField $field)
    {
        $segments = $this->getSegments($field->getName());

        $target =& $this->fields;
        while ($segments) {
            if (!is_array($target)) {
                $target = array();
            }
            $path = array_shift($segments);
            if ('' === $path) {
                $target =& $target[];
            } else {
                $target =& $target[$path];
            }
        }
        $target = $field;
    }

    /**
     * Removes a field and its children from the registry.
     *
     * @param string $name The fully qualified name of the base field
     *
     * @throws \InvalidArgumentException when the name is malformed
     */
    public function remove($name)
    {
        $segments = $this->getSegments($name);
        $target =& $this->fields;
        while (count($segments) > 1) {
            $path = array_shift($segments);
            if (!array_key_exists($path, $target)) {
                return;
            }
            $target =& $target[$path];
        }
        unset($target[array_shift($segments)]);
    }

    /**
     * Returns the value of the field and its children.
     *
     * @param string $name The fully qualified name of the field
     *
     * @return mixed The value of the field
     *
     * @throws \InvalidArgumentException when the name is malformed
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function &get($name)
    {
        $segments = $this->getSegments($name);
        $target =& $this->fields;
        while ($segments) {
            $path = array_shift($segments);
            if (!array_key_exists($path, $target)) {
                throw new \InvalidArgumentException(sprintf('Unreachable field "%s"', $path));
            }
            $target =& $target[$path];
        }

        return $target;
    }

    /**
     * Tests whether the form has the given field.
     *
     * @param string $name The fully qualified name of the field
     *
     * @return Boolean Whether the form has the given field
     */
    public function has($name)
    {
        try {
            $this->get($name);

            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Set the value of a field and its children.
     *
     * @param string $name  The fully qualified name of the field
     * @param mixed  $value The value
     *
     * @throws \InvalidArgumentException when the name is malformed
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function set($name, $value)
    {
        $target =& $this->get($name);
        if (is_array($value)) {
            $fields = self::create($name, $value);
            foreach ($fields->all() as $k => $v) {
                $this->set($k, $v);
            }
        } else {
            $target->setValue($value);
        }
    }

    /**
     * Returns the list of field with their value.
     *
     * @return array The list of fields as array((string) Fully qualified name => (mixed) value)
     */
    public function all()
    {
        return $this->walk($this->fields, $this->base);
    }

    /**
     * Creates an instance of the class.
     *
     * This function is made private because it allows overriding the $base and
     * the $values properties without any type checking.
     *
     * @param string $base   The fully qualified name of the base field
     * @param array  $values The values of the fields
     *
     * @return FormFieldRegistry
     */
    private static function create($base, array $values)
    {
        $registry = new static();
        $registry->base = $base;
        $registry->fields = $values;

        return $registry;
    }

    /**
     * Transforms a PHP array in a list of fully qualified name / value.
     *
     * @param array  $array  The PHP array
     * @param string $base   The name of the base field
     * @param array  $output The initial values
     *
     * @return array The list of fields as array((string) Fully qualified name => (mixed) value)
     */
    private function walk(array $array, $base = '', array &$output = array())
    {
        foreach ($array as $k => $v) {
            $path = empty($base) ? $k : sprintf("%s[%s]", $base, $k);
            if (is_array($v)) {
                $this->walk($v, $path, $output);
            } else {
                $output[$path] = $v;
            }
        }

        return $output;
    }

    /**
     * Splits a field name into segments as a web browser would do.
     *
     * <code>
     *     getSegments('base[foo][3][]') = array('base', 'foo, '3', '');
     * </code>
     *
     * @param string $name The name of the field
     *
     * @return array The list of segments
     *
     * @throws \InvalidArgumentException when the name is malformed
     */
    private function getSegments($name)
    {
        if (preg_match('/^(?P<base>[^[]+)(?P<extra>(\[.*)|$)/', $name, $m)) {
            $segments = array($m['base']);
            while (preg_match('/^\[(?P<segment>.*?)\](?P<extra>.*)$/', $m['extra'], $m)) {
                $segments[] = $m['segment'];
            }

            return $segments;
        }

        throw new \InvalidArgumentException(sprintf('Malformed field path "%s"', $name));
    }
}
