<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\AlreadyBoundException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Renderer\RendererInterface;
use Symfony\Component\Form\Renderer\TableRenderer;
use Symfony\Component\Form\Iterator\RecursiveFieldsWithPropertyPathIterator;
use Symfony\Component\I18N\TranslatorInterface;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * FieldGroup represents an array of widgets bind to names and values.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id: FieldGroup.php 247 2010-02-01 09:24:55Z bernhard $
 */
class FieldGroup extends Field implements \IteratorAggregate, FieldGroupInterface
{
    /**
     * Contains all the fields of this group
     * @var array
     */
    protected $fields = array();

    /**
     * Contains the names of bound values who don't belong to any fields
     * @var array
     */
    protected $extraFields = array();

    /**
     * Constructor
     *
     * @see FieldInterface::__construct()
     */
    public function __construct($key, array $options = array())
    {
        // set the default renderer before calling the configure() method
        $this->setRenderer(new TableRenderer());

        parent::__construct($key, $options);
    }

    /**
     * Clones this group
     */
    public function __clone()
    {
        foreach ($this->fields as $name => $field) {
            $field = clone $field;
            // this condition is only to "bypass" a PHPUnit bug with mocks
            if (null !== $field->getParent()) {
                $field->setParent($this);
            }
            $this->fields[$name] = $field;
        }
    }

    /**
     * Adds a new field to this group. A field must have a unique name within
     * the group. Otherwise the existing field is overwritten.
     *
     * If you add a nested group, this group should also be represented in the
     * object hierarchy. If you want to add a group that operates on the same
     * hierarchy level, use merge().
     *
     * <code>
     * class Entity
     * {
     *   public $location;
     * }
     *
     * class Location
     * {
     *   public $longitude;
     *   public $latitude;
     * }
     *
     * $entity = new Entity();
     * $entity->location = new Location();
     *
     * $form = new Form('entity', $entity, $validator);
     *
     * $locationGroup = new FieldGroup('location');
     * $locationGroup->add(new TextField('longitude'));
     * $locationGroup->add(new TextField('latitude'));
     *
     * $form->add($locationGroup);
     * </code>
     *
     * @param FieldInterface $field
     */
    public function add(FieldInterface $field)
    {
        if ($this->isBound()) {
            throw new AlreadyBoundException('You cannot add fields after binding a form');
        }

        $this->fields[$field->getKey()] = $field;

        $field->setParent($this);
        $field->setLocale($this->locale);
        $field->setGenerator($this->generator);

        if ($this->translator !== null) {
            $field->setTranslator($this->translator);
        }

        $data = $this->getTransformedData();

        // if the property "data" is NULL, getTransformedData() returns an empty
        // string
        if (!empty($data) && $field->getPropertyPath() !== null) {
            $field->updateFromObject($data);
        }

        return $field;
    }

    /**
     * Merges a field group into this group. The group must have a unique name
     * within the group. Otherwise the existing field is overwritten.
     *
     * Contrary to added groups, merged groups operate on the same object as
     * the group they are merged into.
     *
     * <code>
     * class Entity
     * {
     *   public $longitude;
     *   public $latitude;
     * }
     *
     * $entity = new Entity();
     *
     * $form = new Form('entity', $entity, $validator);
     *
     * $locationGroup = new FieldGroup('location');
     * $locationGroup->add(new TextField('longitude'));
     * $locationGroup->add(new TextField('latitude'));
     *
     * $form->merge($locationGroup);
     * </code>
     *
     * @param FieldGroup $group
     */
    public function merge(FieldGroup $group)
    {
        if ($group->isBound()) {
            throw new AlreadyBoundException('A bound form group cannot be merged');
        }

        foreach ($group as $field) {
            $group->remove($field->getKey());
            $this->add($field);

            if (($path = $group->getPropertyPath()) !== null) {
                $field->setPropertyPath($path.'.'.$field->getPropertyPath());
            }
        }

        return $this;
    }

    /**
     * Removes the field with the given key.
     *
     * @param string $key
     */
    public function remove($key)
    {
        $this->fields[$key]->setParent(null);

        unset($this->fields[$key]);
    }

    /**
     * Returns whether a field with the given key exists.
     *
     * @param  string $key
     * @return boolean
     */
    public function has($key)
    {
        return isset($this->fields[$key]);
    }

    /**
     * Returns the field with the given key.
     *
     * @param  string $key
     * @return FieldInterface
     */
    public function get($key)
    {
        if (isset($this->fields[$key])) {
            return $this->fields[$key];
        }

        throw new \InvalidArgumentException(sprintf('Field "%s" does not exist.', $key));
    }

    /**
     * Returns all fields in this group
     *
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Returns an array of hidden fields from the current schema.
     *
     * @param boolean $recursive Whether to recur through embedded schemas
     *
     * @return array
     */
    public function getHiddenFields($recursive = true)
    {
        $fields = array();

        foreach ($this->fields as $field) {
            if ($field instanceof FieldGroup) {
                if ($recursive) {
                    $fields = array_merge($fields, $field->getHiddenFields($recursive));
                }
            } else if ($field->isHidden()) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Initializes the field group with an object to operate on
     *
     * @see FieldInterface
     */
    public function setData($data)
    {
        parent::setData($data);

        // get transformed data and pass its values to child fields
        $data = $this->getTransformedData();

        if (!empty($data) && !is_array($data) && !is_object($data)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type object or array, %s given', gettype($data)));
        }

        if (!empty($data)) {
            $iterator = new RecursiveFieldsWithPropertyPathIterator($this);
            $iterator = new \RecursiveIteratorIterator($iterator);

            foreach ($iterator as $field) {
                $field->updateFromObject($data);
            }
        }
    }

    /**
     * Returns the data of the field as it is displayed to the user.
     *
     * @see FieldInterface
     */
    public function getDisplayedData()
    {
        $values = array();

        foreach ($this->fields as $key => $field) {
            $values[$key] = $field->getDisplayedData();
        }

        return $values;
    }

    /**
     * Binds POST data to the field, transforms and validates it.
     *
     * @param  string|array $taintedData  The POST data
     * @return boolean                    Whether the form is valid
     */
    public function bind($taintedData)
    {
        if ($taintedData === null) {
            $taintedData = array();
        }

        if (!is_array($taintedData)) {
            throw new UnexpectedTypeException('You must pass an array parameter to the bind() method');
        }

        foreach ($this->fields as $key => $field) {
            if (!isset($taintedData[$key])) {
                $taintedData[$key] = null;
            }
        }

        foreach ($taintedData as $key => $value) {
            if ($this->has($key)) {
                $this->fields[$key]->bind($value);
            }
        }

        $data = $this->getTransformedData();
        $iterator = new RecursiveFieldsWithPropertyPathIterator($this);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $field) {
            $field->updateObject($data);
        }

        // bind and reverse transform the data
        parent::bind($data);

        $this->extraFields = array();

        foreach ($taintedData as $key => $value) {
            if (!$this->has($key)) {
                $this->extraFields[] = $key;
            }
        }
    }

    /**
     * Returns whether this form was bound with extra fields
     *
     * @return boolean
     */
    public function isBoundWithExtraFields()
    {
        // TODO: integrate the field names in the error message
        return count($this->extraFields) > 0;
    }

    /**
     * Returns whether the field is valid.
     *
     * @return boolean
     */
    public function isValid()
    {
        if (!parent::isValid()) {
            return false;
        }

        foreach ($this->fields as $field) {
            if (!$field->isValid()) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function addError($message, PropertyPath $path = null, $type = null)
    {
        if ($path !== null) {
            if ($type === self::FIELD_ERROR && $path->hasNext()) {
                $path->next();

                if ($path->isProperty() && $path->getCurrent() === 'fields') {
                    $path->next();
                }

                if ($this->has($path->getCurrent()) && !$this->get($path->getCurrent())->isHidden()) {
                    $this->get($path->getCurrent())->addError($message, $path, $type);

                    return;
                }
            } else if ($type === self::DATA_ERROR) {
                $iterator = new RecursiveFieldsWithPropertyPathIterator($this);
                $iterator = new \RecursiveIteratorIterator($iterator);

                foreach ($iterator as $field) {
                    if (null !== ($fieldPath = $field->getPropertyPath())) {
                        $fieldPath->rewind();

                        if ($fieldPath->getCurrent() === $path->getCurrent() && !$field->isHidden()) {
                            if ($path->hasNext()) {
                                $path->next();
                            }

                            $field->addError($message, $path, $type);

                            return;
                        }
                    }
                }
            }
        }

        parent::addError($message);
    }

    /**
     * Returns whether the field requires a multipart form.
     *
     * @return boolean
     */
    public function isMultipart()
    {
        foreach ($this->fields as $field) {
            if ($field->isMultipart()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sets the renderer.
     *
     * @param RendererInterface $renderer
     */
    public function setRenderer(RendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Returns the current renderer.
     *
     * @return RendererInterface
     */
    public function getRenderer()
    {
        return $this->renderer;
    }

    /**
     * Delegates the rendering of the field to the renderer set.
     *
     * @return string The rendered widget
     */
    public function render(array $attributes = array())
    {
        $this->injectLocaleAndTranslator($this->renderer);

        return $this->renderer->render($this, $attributes);
    }

    /**
     * Delegates the rendering of the field to the renderer set.
     *
     * @return string The rendered widget
     */
    public function renderErrors()
    {
        $this->injectLocaleAndTranslator($this->renderer);

        return $this->renderer->renderErrors($this);
    }
    /**
     * Renders hidden form fields.
     *
     * @param boolean $recursive False will prevent hidden fields from embedded forms from rendering
     *
     * @return string
     */
    public function renderHiddenFields($recursive = true)
    {
        $output = '';

        foreach ($this->getHiddenFields($recursive) as $field) {
            $output .= $field->render();
        }

        return $output;
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
        return $this->has($key);
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
        return $this->get($key);
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
        throw new \LogicException('Use the method add() to add fields');
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
        return $this->remove($key);
    }

    /**
     * Returns the iterator for this group.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->fields);
    }

    /**
     * Returns the number of form fields (implements the \Countable interface).
     *
     * @return integer The number of embedded form fields
     */
    public function count()
    {
        return count($this->fields);
    }

    /**
     * Sets the locale of this field.
     *
     * @see Localizable
     */
    public function setLocale($locale)
    {
        parent::setLocale($locale);

        foreach ($this->fields as $field) {
            $field->setLocale($locale);
        }
    }

    /**
     * Sets the translator of this field.
     *
     * @see Translatable
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        parent::setTranslator($translator);

        foreach ($this->fields as $field) {
            $field->setTranslator($translator);
        }
    }

    /**
     * Distributes the generator among all nested fields
     *
     * @param HtmlGeneratorInterface $generator
     */
    public function setGenerator(HtmlGeneratorInterface $generator)
    {
        parent::setGenerator($generator);

        // TESTME
        foreach ($this->fields as $field) {
            $field->setGenerator($generator);
        }
    }
}
