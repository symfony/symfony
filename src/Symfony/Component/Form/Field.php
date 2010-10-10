<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Form\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\Form\ValueTransformer\TransformationFailedException;

abstract class Field extends Configurable implements FieldInterface
{
    protected $taintedData = null;
    protected $locale = null;

    private $errors = array();
    private $key = '';
    private $parent = null;
    private $bound = false;
    private $required = null;
    private $data = null;
    private $transformedData = null;
    private $valueTransformer = null;
    private $propertyPath = null;

    public function __construct($key, array $options = array())
    {
        $this->addOption('trim', true);
        $this->addOption('required', true);
        $this->addOption('disabled', false);
        $this->addOption('property_path', (string)$key);

        $this->key = (string)$key;

        if ($this->locale === null) {
            $this->locale = class_exists('\Locale', false) ? \Locale::getDefault() : 'en';
        }

        parent::__construct($options);

        $this->transformedData = $this->transform($this->data);
        $this->required = $this->getOption('required');

        $this->setPropertyPath($this->getOption('property_path'));
    }

    /**
     * Clones this field.
     */
    public function __clone()
    {
        // TODO
    }

    public function getAttributes()
    {
        return array();
    }

    /**
     * Returns the data of the field as it is displayed to the user.
     *
     * @return string|array  When the field is not bound, the transformed
     *                       default data is returned. When the field is bound,
     *                       the bound data is returned.
     */
    public function getDisplayedData()
    {
        return $this->getTransformedData();
    }

    /**
     * Returns the data transformed by the value transformer
     *
     * @return string
     */
    protected function getTransformedData()
    {
        return $this->transformedData;
    }

    /**
     * {@inheritDoc}
     */
    public function setPropertyPath($propertyPath)
    {
        $this->propertyPath = $propertyPath === null || $propertyPath === '' ? null : new PropertyPath($propertyPath);
    }

    /**
     * {@inheritDoc}
     */
    public function getPropertyPath()
    {
        return $this->propertyPath;
    }

    /**
     * {@inheritDoc}
     */
    public function setKey($key)
    {
        $this->key = (string)$key;
    }

    /**
     * {@inheritDoc}
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return is_null($this->parent) ? $this->key : $this->parent->getName().'['.$this->key.']';
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return is_null($this->parent) ? $this->key : $this->parent->getId().'_'.$this->key;
    }

    /**
     * {@inheritDoc}
     */
    public function setRequired($required)
    {
        $this->required = $required;
    }

    /**
     * {@inheritDoc}
     */
    public function isRequired()
    {
        if (is_null($this->parent) || $this->parent->isRequired()) {
            return $this->required;
        } else {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isDisabled()
    {
        if (is_null($this->parent) || !$this->parent->isDisabled()) {
            return $this->getOption('disabled');
        } else {
            return true;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isMultipart()
    {
        return false;
    }

    /**
     * Returns true if the widget is hidden.
     *
     * @return Boolean true if the widget is hidden, false otherwise
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function setParent(FieldInterface $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * Returns the parent field.
     *
     * @return FieldInterface  The parent field
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Updates the field with default data
     *
     * @see FieldInterface
     */
    public function setData($data)
    {
        $this->data = $data;
        $this->transformedData = $this->transform($data);
    }

    /**
     * Binds POST data to the field, transforms and validates it.
     *
     * @param  string|array $taintedData  The POST data
     * @return boolean                    Whether the form is valid
     * @throws AlreadyBoundException      when the field is already bound
     */
    public function bind($taintedData)
    {
        $this->transformedData = (is_array($taintedData) || is_object($taintedData)) ? $taintedData : (string)$taintedData;
        $this->bound = true;
        $this->errors = array();

        if (is_string($this->transformedData) && $this->getOption('trim')) {
            $this->transformedData = trim($this->transformedData);
        }

        try {
            $this->data = $this->processData($this->reverseTransform($this->transformedData));
            $this->transformedData = $this->transform($this->data);
        } catch (TransformationFailedException $e) {
            // TODO better text
            // TESTME
            $this->addError('invalid (localized)');
        }
    }

    /**
     * Processes the bound reverse-transformed data.
     *
     * This method can be overridden if you want to modify the data entered
     * by the user. Note that the data is already in reverse transformed format.
     *
     * This method will not be called if reverse transformation fails.
     *
     * @param  mixed $data
     * @return mixed
     */
    protected function processData($data)
    {
        return $data;
    }

    /**
     * Returns the normalized data of the field.
     *
     * @return mixed  When the field is not bound, the default data is returned.
     *                When the field is bound, the normalized bound data is
     *                returned if the field is valid, null otherwise.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Adds an error to the field.
     *
     * @see FieldInterface
     */
    public function addError($messageTemplate, array $messageParameters = array(), PropertyPathIterator $pathIterator = null, $type = null)
    {
        $this->errors[] = array($messageTemplate, $messageParameters);
    }

    /**
     * Returns whether the field is bound.
     *
     * @return boolean  true if the form is bound to input values, false otherwise
     */
    public function isBound()
    {
        return $this->bound;
    }

    /**
     * Returns whether the field is valid.
     *
     * @return boolean
     */
    public function isValid()
    {
        return $this->isBound() ? count($this->errors)==0 : false; // TESTME
    }

    /**
     * Returns weather there are errors.
     *
     * @return boolean  true if form is bound and not valid
     */
    public function hasErrors()
    {
        return $this->isBound() && !$this->isValid();
    }

    /**
     * Returns all errors
     *
     * @return array  An array of errors that occured during binding
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Sets the locale of this field.
     *
     * @see Localizable
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        if ($this->valueTransformer !== null && $this->valueTransformer instanceof Localizable) {
            $this->valueTransformer->setLocale($locale);
        }
    }

    /**
     * Injects the locale into the given object, if set.
     *
     * The locale is injected only if the object implements Localizable.
     *
     * @param object $object
     */
    protected function injectLocale($object)
    {
        if ($object instanceof Localizable) {
            $object->setLocale($this->locale);
        }
    }

    /**
     * Sets the ValueTransformer.
     *
     * @param ValueTransformerInterface $valueTransformer
     */
    public function setValueTransformer(ValueTransformerInterface $valueTransformer)
    {
        $this->injectLocale($valueTransformer);

        $this->valueTransformer = $valueTransformer;
    }

    /**
     * Returns the ValueTransformer.
     *
     * @return ValueTransformerInterface
     */
    public function getValueTransformer()
    {
        return $this->valueTransformer;
    }

    /**
     * Transforms the value if a value transformer is set.
     *
     * @param  mixed $value  The value to transform
     * @return string
     */
    protected function transform($value)
    {
        if ($value === null) {
            return '';
        } else if (null === $this->valueTransformer) {
            return $value;
        } else {
            return $this->valueTransformer->transform($value);
        }
    }

    /**
     * Reverse transforms a value if a value transformer is set.
     *
     * @param  string $value  The value to reverse transform
     * @return mixed
     */
    protected function reverseTransform($value)
    {
        if ($value === '') {
            return null;
        } else if (null === $this->valueTransformer) {
            return $value;
        } else {
            return $this->valueTransformer->reverseTransform($value, $this->data);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateFromObject(&$objectOrArray)
    {
        // TODO throw exception if not object or array
        if ($this->propertyPath !== null) {
            $this->setData($this->propertyPath->getValue($objectOrArray));
        } else {
            // pass object through if the property path is empty
            $this->setData($objectOrArray);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateObject(&$objectOrArray)
    {
        // TODO throw exception if not object or array

        if ($this->propertyPath !== null) {
            $this->propertyPath->setValue($objectOrArray, $this->getData());
        }
    }
}
