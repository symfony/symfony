<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Form;

use Symfony\Component\Templating\Engine;
use Symfony\Component\Form\FieldInterface as FormFieldInterface;
use Symfony\Component\Form\HybridField;
use Symfony\Component\Form\FieldGroupInterface;
use Symfony\Bundle\FrameworkBundle\Templating\HtmlGeneratorInterface;
use Symfony\Component\OutputEscaper\SafeDecoratorInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class BaseField implements FieldInterface, SafeDecoratorInterface
{
    protected $engine;
    protected $field;
    protected $generator;
    protected $theme;
    protected $doctype;

    public function __construct(FormFieldInterface $field, Engine $engine, HtmlGeneratorInterface $generator, $theme, $doctype)
    {
        $this->field = $field;
        $this->engine = $engine;
        $this->generator = $generator;
        $this->theme = $theme;
    }

    public function getIterator()
    {
        if (!$this->field instanceof FieldGroupInterface) {
            throw new \LogicException(sprintf('Cannot iterate a non group field (%s)', $this->field->getKey()));
        }

        $fields = array();
        foreach ($this->field->getFields() as $field) {
            if (!$field->isHidden()) {
                $fields[] = $field;
            }
        }

        return new \ArrayIterator($this->wrapFields($fields));
    }

    /**
     * Returns true if the bound field exists (implements the \ArrayAccess interface).
     *
     * @param string $key The key of the bound field
     *
     * @return Boolean true if the widget exists, false otherwise
     */
    public function offsetExists($key)
    {
        if (!$this->field instanceof FieldGroupInterface) {
            throw new \LogicException(sprintf('Cannot access a non group field as an array (%s)', $this->field->getKey()));
        }

        return $this->field->has($key);
    }

    /**
     * Returns the form field associated with the name (implements the \ArrayAccess interface).
     *
     * @param string $key The offset of the value to get
     *
     * @return Field A form field instance
     */
    public function offsetGet($key)
    {
        if (!$this->field instanceof FieldGroupInterface) {
            throw new \LogicException(sprintf('Cannot access a non group field as an array (%s)', $this->field->getKey()));
        }

        return $this->createField($this->field->get($key));
    }

    /**
     * Throws an exception saying that values cannot be set (implements the \ArrayAccess interface).
     *
     * @param string $offset (ignored)
     * @param string $value (ignored)
     *
     * @throws \LogicException
     */
    public function offsetSet($key, $field)
    {
        throw new \LogicException('This helper is read-only');
    }

    /**
     * Throws an exception saying that values cannot be unset (implements the \ArrayAccess interface).
     *
     * @param string $key
     *
     * @throws \LogicException
     */
    public function offsetUnset($key)
    {
        throw new \LogicException('This helper is read-only');
    }

    protected function wrapFields($fields)
    {
        foreach ($fields as $id => $field) {
            $fields[$id] = $this->createField($field);
        }

        return $fields;
    }

    protected function createField(FormFieldInterface $field)
    {
        if ($field instanceof FieldGroupInterface && !$field instanceof HybridField) {
            return new FieldGroup($field, $this->engine, $this->generator, $this->theme, $this->doctype);
        }

        return new Field($field, $this->engine, $this->generator, $this->theme, $this->doctype);
    }
}
