<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Form\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\Form\ValueTransformer\TransformationFailedException;
use Symfony\Component\Form\Renderer\RendererInterface;
use Symfony\Component\Form\Renderer\Plugin\PluginInterface;
use Symfony\Component\Form\Filter\FilterChain;
use Symfony\Component\Form\Filter\FilterInterface;
use Symfony\Component\Form\EventListener\EventManager;
use Symfony\Component\Form\EventListener\EventListenerInterface;

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
 * calling setValueTransformer() in the configure() method.
 *
 * In some cases though it makes sense to make format (1) configurable. To
 * demonstrate this, let's extend our above date field to store the value
 * either as "Y-m-d" string or as timestamp. Internally we still want to
 * use a DateTime object for processing. To convert the data from string/integer
 * to DateTime you can set a normalization transformer by calling
 * setNormalizationTransformer() in configure(). The normalized data is then
 * converted to the displayed data as described before.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class Field implements FieldInterface
{
    private $errors = array();
    private $key = '';
    private $parent;
    private $submitted = false;
    private $required;
    private $data;
    private $normalizedData;
    private $transformedData = '';
    private $normalizationTransformer;
    private $valueTransformer;
    private $propertyPath;
    private $transformationSuccessful = true;
    private $renderer;
    private $hidden = false;
    private $trim = true;
    private $disabled = false;
    private $filterChain;
    private $eventManager;

    public function __construct($key = null)
    {
        $this->key = (string)$key;
        // TODO should be injected instead
        $this->filterChain = new FilterChain(Filters::$all);
        $this->eventManager = new EventManager(Events::$all);
    }

    /**
     * @deprecated
     */
    public function prependFilter(FilterInterface $filter)
    {
        $this->filterChain->prependFilter($filter);

        return $this;
    }

    /**
     * @deprecated
     */
    public function appendFilter(FilterInterface $filter)
    {
        $this->filterChain->appendFilter($filter);

        return $this;
    }

    /**
     * @deprecated
     */
    public function addEventListener(EventListenerInterface $listener)
    {
        $this->eventManager->addEventListener($listener);

        return $this;
    }

    /**
     * Clones this field.
     */
    public function __clone()
    {
        // TODO
    }

    /**
     * Returns the data of the field as it is displayed to the user.
     *
     * @return string|array  When the field is not submitted, the transformed
     *                       default data is returned. When the field is submitted,
     *                       the submitted data is returned.
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
    public function getTransformedData()
    {
        return $this->transformedData;
    }

    /**
     * {@inheritDoc}
     */
    public function setPropertyPath($propertyPath)
    {
        $this->propertyPath = null === $propertyPath || '' === $propertyPath ? null : new PropertyPath($propertyPath);

        return $this;
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
    public function setRequired($required)
    {
        $this->required = $required;

        return $this;
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

    public function setDisabled($disabled)
    {
        $this->disabled = $disabled;

        return $this;
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
    public function isMultipart()
    {
        return false;
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

    /**
     * Updates the field with default data
     *
     * @see FieldInterface
     */
    public function setData($appData)
    {
        $this->eventManager->triggerEvent(Events::preSetData, $appData);

        // Hook to change content of the data
        $appData = $this->filterChain->filter(Filters::filterSetData, $appData);

        // Treat data as strings unless a value transformer exists
        if (null === $this->valueTransformer && is_scalar($appData)) {
            $appData = (string)$appData;
        }

        // Synchronize representations - must not change the content!
        $normData = $this->normalize($appData);
        $clientData = $this->transform($normData);

        $this->data = $appData;
        $this->normalizedData = $normData;
        $this->transformedData = $clientData;

        $this->eventManager->triggerEvent(Events::postSetData);

        return $this;
    }

    /**
     * Binds POST data to the field, transforms and validates it.
     *
     * @param  string|array $data  The POST data
     */
    public function submit($clientData)
    {
        if (is_scalar($clientData) || null === $clientData) {
            $clientData = (string)$clientData;
        }

        $this->eventManager->triggerEvent(Events::preBind, $clientData);

        if (is_string($clientData) && $this->trim) {
            $clientData = trim($clientData);
        }

        $appData = null;
        $normData = null;

        // Hook to change content of the data submitted by the browser
        $clientData = $this->filterChain->filter(Filters::filterBoundDataFromClient, $clientData);

        try {
            // Normalize data to unified representation
            $normData = $this->reverseTransform($clientData);
            $this->transformationSuccessful = true;
        } catch (TransformationFailedException $e) {
            $this->transformationSuccessful = false;
        }

        if ($this->transformationSuccessful) {
            // Hook to change content of the data in the normalized
            // representation
            $normData = $this->filterChain->filter(Filters::filterBoundData, $normData);

            // Synchronize representations - must not change the content!
            $appData = $this->denormalize($normData);
            $clientData = $this->transform($normData);
        }

        $this->submitted = true;
        $this->errors = array();
        $this->data = $appData;
        $this->normalizedData = $normData;
        $this->transformedData = $clientData;

        $this->eventManager->triggerEvent(Events::postBind);
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
     * @return mixed  When the field is not submitted, the default data is returned.
     *                When the field is submitted, the normalized submitted data is
     *                returned if the field is valid, null otherwise.
     */
    public function getNormalizedData()
    {
        return $this->normalizedData;
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
     * Returns whether the field is submitted.
     *
     * @return Boolean  true if the form is submitted to input values, false otherwise
     */
    public function isSubmitted()
    {
        return $this->submitted;
    }

    /**
     * Returns whether the submitted value could be reverse transformed correctly
     *
     * @return Boolean
     */
    public function isTransformationSuccessful()
    {
        return $this->transformationSuccessful;
    }

    /**
     * Returns whether the field is valid.
     *
     * @return Boolean
     */
    public function isValid()
    {
        return $this->isSubmitted() && !$this->hasErrors(); // TESTME
    }

    /**
     * Returns whether or not there are errors.
     *
     * @return Boolean  true if form is submitted and not valid
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
     * @return array  An array of FieldError instances that occurred during submitting
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Sets the ValueTransformer.
     *
     * @param ValueTransformerInterface $valueTransformer
     */
    public function setNormalizationTransformer(ValueTransformerInterface $normalizationTransformer = null)
    {
        $this->normalizationTransformer = $normalizationTransformer;

        return $this;
    }

    /**
     * Returns the ValueTransformer.
     *
     * @return ValueTransformerInterface
     */
    public function getNormalizationTransformer()
    {
        return $this->normalizationTransformer;
    }

    /**
     * Sets the ValueTransformer.
     *
     * @param ValueTransformerInterface $valueTransformer
     */
    public function setValueTransformer(ValueTransformerInterface $valueTransformer = null)
    {
        $this->valueTransformer = $valueTransformer;

        return $this;
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

    public function setTrim($trim)
    {
        $this->trim = $trim;

        return $this;
    }

    public function getTrim()
    {
        return $this->trim;
    }

    /**
     * Sets the renderer
     *
     * @param RendererInterface $renderer
     */
    public function setRenderer(RendererInterface $renderer)
    {
        $this->renderer = $renderer;

        return $this;
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

    public function addRendererPlugin(PluginInterface $plugin)
    {
        $this->renderer->addPlugin($plugin);

        return $this;
    }

    public function setRendererVar($name, $value)
    {
        $this->renderer->setVar($name, $value);

        return $this;
    }

    /**
     * Normalizes the value if a normalization transformer is set
     *
     * @param  mixed $value  The value to transform
     * @return string
     */
    protected function normalize($value)
    {
        if (null === $this->normalizationTransformer) {
            return $value;
        }
        return $this->normalizationTransformer->transform($value);
    }

    /**
     * Reverse transforms a value if a normalization transformer is set.
     *
     * @param  string $value  The value to reverse transform
     * @return mixed
     */
    protected function denormalize($value)
    {
        if (null === $this->normalizationTransformer) {
            return $value;
        }
        return $this->normalizationTransformer->reverseTransform($value, $this->data);
    }

    /**
     * Transforms the value if a value transformer is set.
     *
     * @param  mixed $value  The value to transform
     * @return string
     */
    protected function transform($value)
    {
        if (null === $this->valueTransformer) {
            // Scalar values should always be converted to strings to
            // facilitate differentiation between empty ("") and zero (0).
            return null === $value || is_scalar($value) ? (string)$value : $value;
        }
        return $this->valueTransformer->transform($value);
    }

    /**
     * Reverse transforms a value if a value transformer is set.
     *
     * @param  string $value  The value to reverse transform
     * @return mixed
     */
    protected function reverseTransform($value)
    {
        if (null === $this->valueTransformer) {
            return '' === $value ? null : $value;
        }
        return $this->valueTransformer->reverseTransform($value, $this->data);
    }

    /**
     * {@inheritDoc}
     */
    public function readProperty(&$objectOrArray)
    {
        // TODO throw exception if not object or array

        if ($this->propertyPath !== null) {
            $this->setData($this->propertyPath->getValue($objectOrArray));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function writeProperty(&$objectOrArray)
    {
        // TODO throw exception if not object or array

        if ($this->propertyPath !== null) {
            $this->propertyPath->setValue($objectOrArray, $this->getData());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty()
    {
        return null === $this->data || '' === $this->data;
    }
}
