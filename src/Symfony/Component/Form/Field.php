<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Form\DataTransformer\DataTransformerInterface;
use Symfony\Component\Form\DataTransformer\TransformationFailedException;
use Symfony\Component\Form\Validator\FieldValidatorInterface;
use Symfony\Component\Form\Renderer\RendererInterface;
use Symfony\Component\Form\Renderer\Plugin\RendererPluginInterface;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for form fields
 *
 * To implement your own form fields, you need to have a thorough understanding
 * of the data flow within a form field. A form field stores its data in three
 * different representations:
 *
 *   (1) the format required by the form's object
 *   (2) a normalized format for internal processing
 *   (3) the format used for display
 *
 * A date field, for example, may store a date as "Y-m-d" string (1) in the
 * object. To facilitate processing in the field, this value is normalized
 * to a DateTime object (2). In the HTML representation of your form, a
 * localized string (3) is presented to and modified by the user.
 *
 * In most cases, format (1) and format (2) will be the same. For example,
 * a checkbox field uses a Boolean value both for internal processing as for
 * storage in the object. In these cases you simply need to set a value
 * transformer to convert between formats (2) and (3). You can do this by
 * calling setClientTransformer() in the configure() method.
 *
 * In some cases though it makes sense to make format (1) configurable. To
 * demonstrate this, let's extend our above date field to store the value
 * either as "Y-m-d" string or as timestamp. Internally we still want to
 * use a DateTime object for processing. To convert the data from string/integer
 * to DateTime you can set a normalization transformer by calling
 * setNormTransformer() in configure(). The normalized data is then
 * converted to the displayed data as described before.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class Field implements FieldInterface
{
    private $errors = array();
    private $name = '';
    private $parent;
    private $bound = false;
    private $required;
    private $data;
    private $normData;
    private $clientData = '';
    private $normTransformer;
    private $clientTransformer;
    private $transformationSuccessful = true;
    private $validator;
    private $renderer;
    private $disabled = false;
    private $dispatcher;
    private $attributes;

    public function __construct($name, EventDispatcherInterface $dispatcher,
        RendererInterface $renderer, DataTransformerInterface $clientTransformer = null,
        DataTransformerInterface $normTransformer = null,
        FieldValidatorInterface $validator = null, $required = false,
        $disabled = false, array $attributes = array())
    {
        $this->name = (string)$name;
        $this->dispatcher = $dispatcher;
        $this->renderer = $renderer;
        $this->clientTransformer = $clientTransformer;
        $this->normTransformer = $normTransformer;
        $this->validator = $validator;
        $this->required = $required;
        $this->disabled = $disabled;
        $this->attributes = $attributes;

        $renderer->setField($this);

        $this->setData(null);
    }

    /**
     * Cloning is not supported
     */
    private function __clone()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function isRequired()
    {
        if (null === $this->parent || $this->parent->isRequired()) {
            return $this->required;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isDisabled()
    {
        if (null === $this->parent || !$this->parent->isDisabled()) {
            return $this->disabled;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function setParent(FieldInterface $parent = null)
    {
        $this->parent = $parent;

        return $this;
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
     * Returns whether the field has a parent.
     *
     * @return Boolean
     */
    public function hasParent()
    {
        return null !== $this->parent;
    }

    /**
     * Returns the root of the form tree
     *
     * @return FieldInterface  The root of the tree
     */
    public function getRoot()
    {
        return $this->parent ? $this->parent->getRoot() : $this;
    }

    /**
     * Returns whether the field is the root of the form tree
     *
     * @return Boolean
     */
    public function isRoot()
    {
        return !$this->hasParent();
    }

    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    public function getAttribute($name)
    {
        return $this->attributes[$name];
    }

    /**
     * Updates the field with default data
     *
     * @see FieldInterface
     */
    public function setData($appData)
    {
        $event = new DataEvent($this, $appData);
        $this->dispatcher->dispatch(Events::preSetData, $event);

        // Hook to change content of the data
        $event = new FilterDataEvent($this, $appData);
        $this->dispatcher->dispatch(Events::filterSetData, $event);
        $appData = $event->getData();

        // Treat data as strings unless a value transformer exists
        if (null === $this->clientTransformer && is_scalar($appData)) {
            $appData = (string)$appData;
        }

        // Synchronize representations - must not change the content!
        $normData = $this->toNorm($appData);
        $clientData = $this->toClient($normData);

        $this->data = $appData;
        $this->normData = $normData;
        $this->clientData = $clientData;

        $event = new DataEvent($this, $appData);
        $this->dispatcher->dispatch(Events::postSetData, $event);

        return $this;
    }

    /**
     * Binds POST data to the field, transforms and validates it.
     *
     * @param  string|array $data  The POST data
     */
    public function bind($clientData)
    {
        if (is_scalar($clientData) || null === $clientData) {
            $clientData = (string)$clientData;
        }

        $event = new DataEvent($this, $clientData);
        $this->dispatcher->dispatch(Events::preBind, $event);

        $appData = null;
        $normData = null;

        // Hook to change content of the data bound by the browser
        $event = new FilterDataEvent($this, $clientData);
        $this->dispatcher->dispatch(Events::filterBoundClientData, $event);
        $clientData = $event->getData();

        try {
            // Normalize data to unified representation
            $normData = $this->fromClient($clientData);
            $this->transformationSuccessful = true;
        } catch (TransformationFailedException $e) {
            $this->transformationSuccessful = false;
        }

        if ($this->transformationSuccessful) {
            // Hook to change content of the data in the normalized
            // representation
            $event = new FilterDataEvent($this, $normData);
            $this->dispatcher->dispatch(Events::filterBoundNormData, $event);
            $normData = $event->getData();

            // Synchronize representations - must not change the content!
            $appData = $this->fromNorm($normData);
            $clientData = $this->toClient($normData);
        }

        $this->bound = true;
        $this->errors = array();
        $this->data = $appData;
        $this->normData = $normData;
        $this->clientData = $clientData;

        $event = new DataEvent($this, $clientData);
        $this->dispatcher->dispatch(Events::postBind, $event);

        if ($this->validator) {
            $this->validator->validate($this);
        }
    }

    /**
     * Returns the data in the format needed for the underlying object.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns the normalized data of the field.
     *
     * @return mixed  When the field is not bound, the default data is returned.
     *                When the field is bound, the normalized bound data is
     *                returned if the field is valid, null otherwise.
     */
    public function getNormData()
    {
        return $this->normData;
    }

    /**
     * Returns the data transformed by the value transformer
     *
     * @return string
     */
    public function getClientData()
    {
        return $this->clientData;
    }

    /**
     * Adds an error to the field.
     *
     * @see FieldInterface
     */
    public function addError(Error $error, PropertyPathIterator $pathIterator = null)
    {
        $this->errors[] = $error;
    }

    /**
     * Returns whether the field is bound.
     *
     * @return Boolean  true if the form is bound to input values, false otherwise
     */
    public function isBound()
    {
        return $this->bound;
    }

    /**
     * Returns whether the bound value could be reverse transformed correctly
     *
     * @return Boolean
     */
    public function isTransformationSuccessful()
    {
        return $this->transformationSuccessful;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty()
    {
        return null === $this->data || '' === $this->data;
    }

    /**
     * Returns whether the field is valid.
     *
     * @return Boolean
     */
    public function isValid()
    {
        return $this->isBound() && !$this->hasErrors(); // TESTME
    }

    /**
     * Returns whether or not there are errors.
     *
     * @return Boolean  true if form is bound and not valid
     */
    public function hasErrors()
    {
        // Don't call isValid() here, as its semantics are slightly different
        // Field groups are not valid if their children are invalid, but
        // hasErrors() returns only true if a field/field group itself has
        // errors
        return count($this->errors) > 0;
    }

    /**
     * Returns all errors
     *
     * @return array  An array of FieldError instances that occurred during binding
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Returns the DataTransformer.
     *
     * @return DataTransformerInterface
     */
    public function getNormTransformer()
    {
        return $this->normTransformer;
    }

    /**
     * Returns the DataTransformer.
     *
     * @return DataTransformerInterface
     */
    public function getClientTransformer()
    {
        return $this->clientTransformer;
    }

    /**
     * Returns the renderer
     *
     * @return RendererInterface
     */
    public function getRenderer()
    {
        return $this->renderer;
    }

    /**
     * Normalizes the value if a normalization transformer is set
     *
     * @param  mixed $value  The value to transform
     * @return string
     */
    protected function toNorm($value)
    {
        if (null === $this->normTransformer) {
            return $value;
        }
        return $this->normTransformer->transform($value);
    }

    /**
     * Reverse transforms a value if a normalization transformer is set.
     *
     * @param  string $value  The value to reverse transform
     * @return mixed
     */
    protected function fromNorm($value)
    {
        if (null === $this->normTransformer) {
            return $value;
        }
        return $this->normTransformer->reverseTransform($value);
    }

    /**
     * Transforms the value if a value transformer is set.
     *
     * @param  mixed $value  The value to transform
     * @return string
     */
    protected function toClient($value)
    {
        if (null === $this->clientTransformer) {
            // Scalar values should always be converted to strings to
            // facilitate differentiation between empty ("") and zero (0).
            return null === $value || is_scalar($value) ? (string)$value : $value;
        }
        return $this->clientTransformer->transform($value);
    }

    /**
     * Reverse transforms a value if a value transformer is set.
     *
     * @param  string $value  The value to reverse transform
     * @return mixed
     */
    protected function fromClient($value)
    {
        if (null === $this->clientTransformer) {
            return '' === $value ? null : $value;
        }
        return $this->clientTransformer->reverseTransform($value);
    }
}
