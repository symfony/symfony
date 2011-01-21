<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\AlreadyBoundException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\DanglingFieldException;

/**
 * FieldGroup represents an array of widgets bind to names and values.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
     * @inheritDoc
     */
    public function __construct($key, array $options = array())
    {
        $this->addOption('virtual', false);

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
     * @param FieldInterface|string $field
     * @return FieldInterface
     */
    public function add($field)
    {
        if ($this->isBound()) {
            throw new AlreadyBoundException('You cannot add fields after binding a form');
        }

        // if the field is given as string, ask the field factory of the form
        // to create a field
        if (!$field instanceof FieldInterface) {
            if (!is_string($field)) {
                throw new UnexpectedTypeException($field, 'FieldInterface or string');
            }

            if (!$this->getRoot() instanceof Form) {
                throw new DanglingFieldException('Field groups must be added to a form before fields can be created automatically');
            }

            $factory = $this->getRoot()->getFieldFactory();

            if (!$factory) {
                throw new \LogicException('A field factory must be available to automatically create fields');
            }

            $options = func_num_args() > 1 ? func_get_arg(1) : array();
            $field = $factory->getInstance($this->getData(), $field, $options);
        }

        $this->fields[$field->getKey()] = $field;

        $field->setParent($this);

        $data = $this->getTransformedData();

        // if the property "data" is NULL, getTransformedData() returns an empty
        // string
        if (!empty($data)) {
            $field->updateFromProperty($data);
        }

        return $field;
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
     * @return Boolean
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
     * Returns an array of visible fields from the current schema.
     *
     * @return array
     */
    public function getVisibleFields()
    {
        return $this->getFieldsByVisibility(false, false);
    }

    /**
     * Returns an array of visible fields from the current schema.
     *
     * This variant of the method will recursively get all the
     * fields from the nested forms or field groups
     *
     * @return array
     */
    public function getAllVisibleFields()
    {
        return $this->getFieldsByVisibility(false, true);
    }

    /**
     * Returns an array of hidden fields from the current schema.
     *
     * @return array
     */
    public function getHiddenFields()
    {
        return $this->getFieldsByVisibility(true, false);
    }

    /**
     * Returns an array of hidden fields from the current schema.
     *
     * This variant of the method will recursively get all the
     * fields from the nested forms or field groups
     *
     * @return array
     */
    public function getAllHiddenFields()
    {
        return $this->getFieldsByVisibility(true, true);
    }

    /**
     * Returns a filtered array of fields from the current schema.
     *
     * @param Boolean $hidden Whether to return hidden fields only or visible fields only
     * @param Boolean $recursive Whether to recur through embedded schemas
     *
     * @return array
     */
    protected function getFieldsByVisibility($hidden, $recursive)
    {
        $fields = array();
        $hidden = (Boolean)$hidden;

        foreach ($this->fields as $field) {
            if ($field instanceof FieldGroup && $recursive) {
                $fields = array_merge($fields, $field->getFieldsByVisibility($hidden, $recursive));
            } else if ($hidden === $field->isHidden()) {
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
            $this->updateFromObject($data);
        }
    }

    /**
     * Returns the data of the field as it is displayed to the user.
     *
     * @see FieldInterface
     * @return array of field name => value
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
     */
    public function bind($taintedData)
    {
        if (null === $taintedData) {
            $taintedData = array();
        }

        if (!is_array($taintedData)) {
            throw new UnexpectedTypeException($taintedData, 'array');
        }

        foreach ($this->fields as $key => $field) {
            if (!isset($taintedData[$key])) {
                $taintedData[$key] = null;
            }
        }

        $taintedData = $this->preprocessData($taintedData);

        foreach ($taintedData as $key => $value) {
            if ($this->has($key)) {
                $this->fields[$key]->bind($value);
            }
        }

        $data = $this->getTransformedData();

        $this->updateObject($data);

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
     * Updates the child fields from the properties of the given data
     *
     * This method calls updateFromProperty() on all child fields that have a
     * property path set. If a child field has no property path set but
     * implements FieldGroupInterface, updateProperty() is called on its
     * children instead.
     *
     * @param array|object $objectOrArray
     */
    protected function updateFromObject(&$objectOrArray)
    {
        $iterator = new RecursiveFieldIterator($this);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $field) {
            $field->updateFromProperty($objectOrArray);
        }
    }

    /**
     * Updates all properties of the given data from the child fields
     *
     * This method calls updateProperty() on all child fields that have a property
     * path set. If a child field has no property path set but implements
     * FieldGroupInterface, updateProperty() is called on its children instead.
     *
     * @param array|object $objectOrArray
     */
    protected function updateObject(&$objectOrArray)
    {
        $iterator = new RecursiveFieldIterator($this);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $field) {
            $field->updateProperty($objectOrArray);
        }
    }

    /**
     * Processes the bound data before it is passed to the individual fields
     *
     * The data is in the user format.
     *
     * @param  array $data
     * @return array
     */
    protected function preprocessData(array $data)
    {
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function isVirtual()
    {
        return $this->getOption('virtual');
    }

    /**
     * Returns whether this form was bound with extra fields
     *
     * @return Boolean
     */
    public function isBoundWithExtraFields()
    {
        // TODO: integrate the field names in the error message
        return count($this->extraFields) > 0;
    }

    /**
     * Returns whether the field is valid.
     *
     * @return Boolean
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
    public function addError(FieldError $error, PropertyPathIterator $pathIterator = null, $type = null)
    {
        if (null !== $pathIterator) {
            if ($type === self::FIELD_ERROR && $pathIterator->hasNext()) {
                $pathIterator->next();

                if ($pathIterator->isProperty() && $pathIterator->current() === 'fields') {
                    $pathIterator->next();
                }

                if ($this->has($pathIterator->current()) && !$this->get($pathIterator->current())->isHidden()) {
                    $this->get($pathIterator->current())->addError($error, $pathIterator, $type);

                    return;
                }
            } else if ($type === self::DATA_ERROR) {
                $iterator = new RecursiveFieldIterator($this);
                $iterator = new \RecursiveIteratorIterator($iterator);

                foreach ($iterator as $field) {
                    if (null !== ($fieldPath = $field->getPropertyPath())) {
                        if ($fieldPath->getElement(0) === $pathIterator->current() && !$field->isHidden()) {
                            if ($pathIterator->hasNext()) {
                                $pathIterator->next();
                            }

                            $field->addError($error, $pathIterator, $type);

                            return;
                        }
                    }
                }
            }
        }

        parent::addError($error);
    }

    /**
     * Returns whether the field requires a multipart form.
     *
     * @return Boolean
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
}
