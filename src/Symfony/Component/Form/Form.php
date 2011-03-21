<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\MissingOptionsException;
use Symfony\Component\Form\Exception\AlreadyBoundException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\DanglingFieldException;
use Symfony\Component\Form\Exception\FieldDefinitionException;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\DataTransformer\DataTransformerInterface;
use Symfony\Component\Form\DataTransformer\TransformationFailedException;
use Symfony\Component\Form\DataMapper\DataMapperInterface;
use Symfony\Component\Form\Validator\FormValidatorInterface;
use Symfony\Component\Form\Renderer\FormRendererInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Form represents a form.
 *
 * A form is composed of a validator schema and a widget form schema.
 *
 * Form also takes care of CSRF protection by default.
 *
 * A CSRF secret can be any random string. If set to false, it disables the
 * CSRF protection, and if set to null, it forces the form to use the global
 * CSRF secret. If the global CSRF secret is also null, then a random one
 * is generated on the fly.
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
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class Form implements \IteratorAggregate, FormInterface
{
    /**
     * Contains all the children of this group
     * @var array
     */
    private $children = array();

    private $dataMapper;
    private $errors = array();
    private $name = '';
    private $parent;
    private $bound = false;
    private $required;
    private $data;
    private $normData;
    private $clientData;

    /**
     * Contains the names of bound values who don't belong to any children
     * @var array
     */
    private $extraData = array();

    private $normTransformer;
    private $clientTransformer;
    private $transformationSuccessful = true;
    private $validators;
    private $renderer;
    private $readOnly = false;
    private $dispatcher;
    private $attributes;

    public function __construct($name, EventDispatcherInterface $dispatcher,
        FormRendererInterface $renderer = null, DataTransformerInterface $clientTransformer = null,
        DataTransformerInterface $normTransformer = null,
        DataMapperInterface $dataMapper = null, array $validators = array(),
        $required = false, $readOnly = false, array $attributes = array())
    {
        foreach ($validators as $validator) {
            if (!$validator instanceof FormValidatorInterface) {
                throw new UnexpectedTypeException($validator, 'Symfony\Component\Form\Validator\FormValidatorInterface');
            }
        }

        $this->name = (string)$name;
        $this->dispatcher = $dispatcher;
        $this->renderer = $renderer;
        $this->clientTransformer = $clientTransformer;
        $this->normTransformer = $normTransformer;
        $this->validators = $validators;
        $this->dataMapper = $dataMapper;
        $this->required = $required;
        $this->readOnly = $readOnly;
        $this->attributes = $attributes;

        if ($renderer) {
            $renderer->setForm($this);
        }

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
    public function isReadOnly()
    {
        if (null === $this->parent || !$this->parent->isReadOnly()) {
            return $this->readOnly;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function setParent(FormInterface $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Returns the parent field.
     *
     * @return FormInterface  The parent field
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
     * @return FormInterface  The root of the tree
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
     * @see FormInterface
     */
    public function setData($appData)
    {
        $event = new DataEvent($this, $appData);
        $this->dispatcher->dispatch(Events::preSetData, $event);

        // Hook to change content of the data
        $event = new FilterDataEvent($this, $appData);
        $this->dispatcher->dispatch(Events::filterSetData, $event);
        $appData = $event->getData();

        // Fix data if empty
        if (!$this->clientTransformer) {
            if (empty($appData) && !$this->normTransformer && $this->dataMapper) {
                $appData = $this->dataMapper->createEmptyData();
            }

            // Treat data as strings unless a value transformer exists
            if (is_scalar($appData)) {
                $appData = (string)$appData;
            }
        }

        // Synchronize representations - must not change the content!
        $normData = $this->appToNorm($appData);
        $clientData = $this->normToClient($normData);

        $this->data = $appData;
        $this->normData = $normData;
        $this->clientData = $clientData;

        if ($this->dataMapper) {
            // Update child forms from the data
            $this->dataMapper->mapDataToForms($clientData, $this->children);
        }

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
        $extraData = array();

        // Hook to change content of the data bound by the browser
        $event = new FilterDataEvent($this, $clientData);
        $this->dispatcher->dispatch(Events::filterBoundClientData, $event);
        $clientData = $event->getData();

        if (count($this->children) > 0) {
            if (empty($clientData)) {
                $clientData = array();
            }

            if (!is_array($clientData)) {
                throw new UnexpectedTypeException($clientData, 'array');
            }

            foreach ($this->children as $name => $child) {
                if (!isset($clientData[$name])) {
                    $clientData[$name] = null;
                }
            }

            foreach ($clientData as $name => $value) {
                if ($this->has($name)) {
                    $this->children[$name]->bind($value);
                } else {
                    $extraData[$name] = $value;
                }
            }

            // Merge form data from children into existing client data
            if ($this->dataMapper) {
                $clientData = $this->getClientData();

                $this->dataMapper->mapFormsToData($this->children, $clientData);
            }
        }

        try {
            // Normalize data to unified representation
            $normData = $this->clientToNorm($clientData);
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
            $appData = $this->normToApp($normData);
            $clientData = $this->normToClient($normData);
        }

        $this->bound = true;
        $this->errors = array();
        $this->data = $appData;
        $this->normData = $normData;
        $this->clientData = $clientData;
        $this->extraData = $extraData;

        $event = new DataEvent($this, $clientData);
        $this->dispatcher->dispatch(Events::postBind, $event);

        foreach ($this->validators as $validator) {
            $validator->validate($this);
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

    public function getExtraData()
    {
        return $this->extraData;
    }

    /**
     * Adds an error to the field.
     *
     * @see FormInterface
     */
    public function addError(Error $error)
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
        foreach ($this->children as $child) {
            if (!$child->isEmpty()) {
                return false;
            }
        }

        return array() === $this->data || null === $this->data || '' === $this->data;
    }

    /**
     * Returns whether the field is valid.
     *
     * @return Boolean
     */
    public function isValid()
    {
        // TESTME
        if (!$this->isBound() || $this->hasErrors()) {
            return false;
        }

        foreach ($this->children as $child) {
            if (!$child->isValid()) {
                return false;
            }
        }

        return true;
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
     * @return array  An array of FormError instances that occurred during binding
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
     * @return FormRendererInterface
     */
    public function getRenderer()
    {
        return $this->renderer;
    }

    /**
     * Returns all children in this group
     *
     * @return array
     */
    public function getChildren()
    {
        return $this->children;
    }

    public function add(FormInterface $child)
    {
        $this->children[$child->getName()] = $child;

        $child->setParent($this);

        $this->dataMapper->mapDataToForm($this->getClientData(), $child);
    }

    public function remove($name)
    {
        if (isset($this->children[$name])) {
            $this->children[$name]->setParent(null);

            unset($this->children[$name]);
        }
    }

    /**
     * Returns whether a child with the given name exists.
     *
     * @param  string $name
     * @return Boolean
     */
    public function has($name)
    {
        return isset($this->children[$name]);
    }

    /**
     * Returns the child with the given name.
     *
     * @param  string $name
     * @return FormInterface
     */
    public function get($name)
    {
        if (isset($this->children[$name])) {
            return $this->children[$name];
        }

        throw new \InvalidArgumentException(sprintf('Field "%s" does not exist.', $name));
    }

    /**
     * Returns true if the child exists (implements the \ArrayAccess interface).
     *
     * @param string $name The name of the child
     *
     * @return Boolean true if the widget exists, false otherwise
     */
    public function offsetExists($name)
    {
        return $this->has($name);
    }

    /**
     * Returns the form child associated with the name (implements the \ArrayAccess interface).
     *
     * @param string $name The offset of the value to get
     *
     * @return Field A form child instance
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * Throws an exception saying that values cannot be set (implements the \ArrayAccess interface).
     *
     * @param string $offset (ignored)
     * @param string $value (ignored)
     *
     * @throws \LogicException
     */
    public function offsetSet($name, $child)
    {
        throw new \BadMethodCallException('offsetSet() is not supported');
    }

    /**
     * Throws an exception saying that values cannot be unset (implements the \ArrayAccess interface).
     *
     * @param string $name
     *
     * @throws \LogicException
     */
    public function offsetUnset($name)
    {
        throw new \BadMethodCallException('offsetUnset() is not supported');
    }

    /**
     * Returns the iterator for this group.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->children);
    }

    /**
     * Returns the number of form children (implements the \Countable interface).
     *
     * @return integer The number of embedded form children
     */
    public function count()
    {
        return count($this->children);
    }

    /**
     * Normalizes the value if a normalization transformer is set
     *
     * @param  mixed $value  The value to transform
     * @return string
     */
    private function appToNorm($value)
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
    private function normToApp($value)
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
    private function normToClient($value)
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
    private function clientToNorm($value)
    {
        if (null === $this->clientTransformer) {
            return '' === $value ? null : $value;
        }

        return $this->clientTransformer->reverseTransform($value);
    }

    /**
     * Binds a request to the form
     *
     * If the request was a POST request, the data is bound to the form,
     * transformed and written into the form data (an object or an array).
     * You can set the form data by passing it in the second parameter
     * of this method or by passing it in the "data" option of the form's
     * constructor.
     *
     * @param Request $request    The request to bind to the form
     * @param array|object $data  The data from which to read default values
     *                            and where to write bound values
     */
    public function bindRequest(Request $request)
    {
        // Store the bound data in case of a post request
        switch ($request->getMethod()) {
            case 'POST':
            case 'PUT':
                $data = array_replace_recursive(
                    $request->request->get($this->getName(), array()),
                    $request->files->get($this->getName(), array())
                );
                break;
            case 'GET':
                $data = $request->query->get($this->getName(), array());
                break;
            default:
                throw new FormException(sprintf('The request method "%s" is not supported', $request->getMethod()));
        }

        $this->bind($data);
    }

    /**
     * Validates the data of this form
     *
     * This method is called automatically during the validation process.
     *
     * @param ExecutionContext $context  The current validation context
     * @deprecated
     */
    public function validateData(ExecutionContext $context)
    {
        if (is_object($this->getData()) || is_array($this->getData())) {
            $groups = $this->getAttribute('validation_groups');
            $child = $this;

            while (!$groups && $child->hasParent()) {
                $child = $child->getParent();
                $groups = $child->getAttribute('validation_groups');
            }

            if (null === $groups) {
                $groups = array(null);
            }

            $propertyPath = $context->getPropertyPath();
            $graphWalker = $context->getGraphWalker();

            // The Execute constraint is called on class level, so we need to
            // set the property manually
            $context->setCurrentProperty('data');

            // Adjust the property path accordingly
            if (!empty($propertyPath)) {
                $propertyPath .= '.';
            }

            $propertyPath .= 'data';

            foreach ($groups as $group) {
                $graphWalker->walkReference($this->getData(), $group, $propertyPath, true);
            }
        }
    }
}
