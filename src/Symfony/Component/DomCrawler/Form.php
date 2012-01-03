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
use Symfony\Component\DomCrawler\Field\FileFormField;

/**
 * Form represents an HTML form.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Form extends Link implements \ArrayAccess
{
    private $button;
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
     * The values are an array where keys are field names
     * and values are the field values.
     *
     * A field value must be an array when more than one field
     * exists in the form for a given name.
     *
     * @param array $values An array of field values
     *
     * @api
     *
     * @see offsetSet()
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
     *
     * @see convertValues()
     */
    public function getValues()
    {
        return $this->convertValues(function (FormField $field) {
            return !$field instanceof FileFormField && $field->hasValue();
        });
    }

    /**
     * Gets the file field values.
     *
     * @return array An array of file field values.
     *
     * @api
     *
     * @see convertValues()
     */
    public function getFiles()
    {
        if (!in_array($this->getMethod(), array('POST', 'PUT', 'DELETE'))) {
            return array();
        }

        return $this->convertValues(function (FormField $field) {
            return $field instanceof FileFormField;
        });
    }

    /**
     * Gets the field values as PHP.
     *
     * @return array An array of field values.
     *
     * @api
     *
     * @see convertValuesToPhp()
     */
    public function getPhpValues()
    {
        return $this->convertValuesToPhp($this->getValues());
    }

    /**
     * Gets the file field values as PHP.
     *
     * @return array An array of field values.
     *
     * @api
     *
     * @see convertValuesToPhp()
     */
    public function getPhpFiles()
    {
        return $this->convertValuesToPhp($this->getFiles(), true);
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
        foreach ($this->fields as $field) {
            if ($name === $field->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes a field from the form.
     *
     * This can remove more than one field when more
     * than one field has the same name.
     *
     * @param string $name The field name
     *
     * @api
     */
    public function remove($name)
    {
        foreach ($this->fields as $i => $field) {
            if ($name === $field->getName()) {
                unset($this->fields[$i]);
            }
        }

        $this->fields = array_values($this->fields);
    }

    /**
     * Gets a named field.
     *
     * It can return an array of FormField instances when more than one field
     * has the same name in the form.
     *
     * @param string $name The field name
     *
     * @return FormField|array The field instance
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

        $fields = array();
        foreach ($this->fields as $field) {
            if ($name === $field->getName()) {
                $fields[] = $field;
            }
        }

        return 1 == count($fields) ? $fields[0] : $fields;
    }

    /**
     * Appends a named field to the form.
     *
     * @param Field\FormField $field The field
     *
     * @api
     */
    public function set(FormField $field)
    {
        $this->fields[] = $field;
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
     * @return FormField|array The associated Field instance
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * Sets the value of a field.
     *
     * When the named field exists more than once in the form,
     * you must pass an array as a value (one value for each form field);
     * but if the named field only exists once in the form, the value
     * must be a scalar.
     *
     * @param string       $name  The field name
     * @param string|array $value The value of the field
     *
     * @throws \InvalidArgumentException if the field does not exist or if the value is not of the right type (see above)
     */
    public function offsetSet($name, $value)
    {
        $field = $this->get($name);

        if (!is_array($field)) {
            if (is_array($value)) {
                throw new \InvalidArgumentException(sprintf('The form field "%s" value must be a scalar', $name));
            }
            $field->setValue($value);
        } else {
            if (!is_array($value)) {
                throw new \InvalidArgumentException(sprintf('The form field "%s" value must be an array', $name));
            }
            if (count($value) > count($field)) {
                throw new \InvalidArgumentException(sprintf('The form field "%s" has only "%d" possible values ("%d" passed)', $name, count($field), count($value)));
            }

            foreach ($value as $i => $v) {
                $field[$i]->setValue($v);
            }
        }
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
        } elseif('form' != $node->nodeName) {
            throw new \LogicException(sprintf('Unable to submit on a "%s" tag.', $node->nodeName));
        }

        $this->node = $node;
    }

    /**
     * Checks if a field name represents a multi-valued field.
     *
     * The default implementation uses the PHP convention.
     *
     * To disable this behavior, override this method and always
     * return false.
     *
     * @param string $name The field name
     *
     * @return Boolean true if the field name represents a multi-valued field, false otherwise.
     */
    protected function isFieldMultivalued($name)
    {
        return '[]' == substr($name, -2);
    }

    /**
     * Implements the logic to convert form field values to a PHP array.
     *
     * This method returns an array of field values.
     *
     * A field value is either an array for fields that exists multiple times
     * in the form or a scalar otherwise.
     *
     * @param \Closure $filter A filter to exclude some fields from the returned array
     *
     * @return array An array of field values.
     */
    protected function convertValues(\Closure $filter)
    {
        $values = array();
        foreach ($this->fields as $field) {
            if (!$field->isDisabled() && $filter($field)) {
                $name = $field->getName();
                $values[$name][] = $field->getValue();
                $array = $values[$name];
                unset($values[$name]);
                $values[$name] = $array;
            }
        }

        foreach ($values as $k => $v) {
            if (1 == count($v)) {
                $values[$k] = $v[0];
            }
        }

        return $values;
    }

    /**
     * Implements the logic to convert form field values to PHP.
     *
     * This method converts the input values by implementing the same
     * conversions as a normal browser does:
     *
     *   * when several fields have the same name, only the last
     *     value is returned.
     *
     * This method converts the input values by implementing some
     * specific PHP conversions:
     *
     *   * fields with the array notation are converted to
     *     arrays (like foo[bar]);
     *
     *   * field names ending with '[]' are converted to names
     *     without this suffix.
     *
     * @param array   $input   An array of form values
     * @param Boolean $isFiles Whether the input is an array of submitted files or not
     *
     * @return array An array of converted field values.
     */
    protected function convertValuesToPhp($input, $isFiles = false)
    {
        $values = array();
        foreach ($input as $name => $value) {
            if (is_array($value)) {
                if ($this->isFieldMultivalued($name)) {
                    // not very elegant but needed to avoid confusion between
                    // foo[bar] and foo[] by parse_str below (see FormTest::testGetPhpValues()
                    // for an example where it is needed).
                    $name = '____'.substr($name, 0, -2);
                } elseif (!$isFiles) {
                    $value = array_pop($value);
                }
            }

            $values[$name] = $value;
        }

        $qs = http_build_query($values);
        parse_str($qs, $tmp);

        $values = array();
        foreach ($tmp as $key => $value) {
            if ('____' == substr($key, 0, 4)) {
                $key = substr($key, 4);
            }
            $values[$key] = $value;
        }

        return $values;
    }
}
