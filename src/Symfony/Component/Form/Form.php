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

use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\AlreadyBoundException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Form represents a form.
 *
 * A form is composed of a validator schema and a widget form schema.
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
 * calling appendClientTransformer().
 *
 * In some cases though it makes sense to make format (1) configurable. To
 * demonstrate this, let's extend our above date field to store the value
 * either as "Y-m-d" string or as timestamp. Internally we still want to
 * use a DateTime object for processing. To convert the data from string/integer
 * to DateTime you can set a normalization transformer by calling
 * appendNormTransformer(). The normalized data is then
 * converted to the displayed data as described before.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class Form implements \IteratorAggregate, FormInterface
{
    /**
     * The name of this form
     * @var string
     */
    private $name;

    /**
     * The parent of this form
     * @var FormInterface
     */
    private $parent;

    /**
     * The children of this form
     * @var array An array of FormInterface instances
     */
    private $children = array();

    /**
     * The mapper for mapping data to children and back
     * @var DataMapperInterface
     */
    private $dataMapper;

    /**
     * The errors of this form
     * @var array An array of FormError instances
     */
    private $errors = array();

    /**
     * Whether added errors should bubble up to the parent
     * @var Boolean
     */
    private $errorBubbling;

    /**
     * Whether this form is bound
     * @var Boolean
     */
    private $bound = false;

    /**
     * Whether this form may or may not be empty
     * @var Boolean
     */
    private $required;

    /**
     * The form data in application format
     * @var mixed
     */
    private $appData;

    /**
     * The form data in normalized format
     * @var mixed
     */
    private $normData;

    /**
     * The form data in client format
     * @var mixed
     */
    private $clientData;

    /**
     * Data used for the client data when no value is bound
     * @var mixed
     */
    private $emptyData = '';

    /**
     * The bound values that don't belong to any children
     * @var array
     */
    private $extraData = array();

    /**
     * The transformers for transforming from application to normalized format
     * and back
     * @var array An array of DataTransformerInterface
     */
    private $normTransformers;

    /**
     * The transformers for transforming from normalized to client format and
     * back
     * @var array An array of DataTransformerInterface
     */
    private $clientTransformers;

    /**
     * Whether the data in application, normalized and client format is
     * synchronized. Data may not be synchronized if transformation errors
     * occur.
     * @var Boolean
     */
    private $synchronized = true;

    /**
     * The validators attached to this form
     * @var array An array of FormValidatorInterface instances
     */
    private $validators;

    /**
     * Whether this form may only be read, but not bound
     * @var Boolean
     */
    private $disabled = false;

    /**
     * The dispatcher for distributing events of this form
     * @var Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * Key-value store for arbitrary attributes attached to this form
     * @var array
     */
    private $attributes;

    /**
     * The FormTypeInterface instances used to create this form
     * @var array An array of FormTypeInterface
     */
    private $types;

    public function __construct($name, EventDispatcherInterface $dispatcher,
        array $types = array(), array $clientTransformers = array(),
        array $normTransformers = array(),
        DataMapperInterface $dataMapper = null, array $validators = array(),
        $required = false, $disabled = false, $errorBubbling = null,
        $emptyData = null, array $attributes = array())
    {
        $name = (string) $name;

        self::validateName($name);

        foreach ($clientTransformers as $transformer) {
            if (!$transformer instanceof DataTransformerInterface) {
                throw new UnexpectedTypeException($transformer, 'Symfony\Component\Form\DataTransformerInterface');
            }
        }

        foreach ($normTransformers as $transformer) {
            if (!$transformer instanceof DataTransformerInterface) {
                throw new UnexpectedTypeException($transformer, 'Symfony\Component\Form\DataTransformerInterface');
            }
        }

        foreach ($validators as $validator) {
            if (!$validator instanceof FormValidatorInterface) {
                throw new UnexpectedTypeException($validator, 'Symfony\Component\Form\FormValidatorInterface');
            }
        }

        $this->name = $name;
        $this->dispatcher = $dispatcher;
        $this->types = $types;
        $this->clientTransformers = $clientTransformers;
        $this->normTransformers = $normTransformers;
        $this->dataMapper = $dataMapper;
        $this->validators = $validators;
        $this->required = (Boolean) $required;
        $this->disabled = (Boolean) $disabled;
        $this->errorBubbling = (Boolean) $errorBubbling;
        $this->emptyData = $emptyData;
        $this->attributes = $attributes;

        $this->setData(null);
    }

    public function __clone()
    {
        foreach ($this->children as $key => $child) {
            $this->children[$key] = clone $child;
        }
    }

    /**
     * Returns the name by which the form is identified in forms.
     *
     * @return string  The name of the form.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the types used by this form.
     *
     * @return array An array of FormTypeInterface
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * Returns whether the form is required to be filled out.
     *
     * If the form has a parent and the parent is not required, this method
     * will always return false. Otherwise the value set with setRequired()
     * is returned.
     *
     * @return Boolean
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
     * Sets the parent form.
     *
     * @param FormInterface $parent The parent form
     *
     * @return Form The current form
     */
    public function setParent(FormInterface $parent = null)
    {
        if ($this->bound) {
            throw new AlreadyBoundException('You cannot set the parent of a bound form');
        }

        if ('' === $this->getName()) {
            throw new FormException('Form with empty name can not have parent form.');
        }

        $this->parent = $parent;

        return $this;
    }

    /**
     * Returns the parent form.
     *
     * @return FormInterface The parent form
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Returns whether the form has a parent.
     *
     * @return Boolean
     */
    public function hasParent()
    {
        return null !== $this->parent;
    }

    /**
     * Returns the root of the form tree.
     *
     * @return FormInterface  The root of the tree
     */
    public function getRoot()
    {
        return $this->parent ? $this->parent->getRoot() : $this;
    }

    /**
     * Returns whether the form is the root of the form tree.
     *
     * @return Boolean
     */
    public function isRoot()
    {
        return !$this->hasParent();
    }

    /**
     * Returns whether the form has an attribute with the given name.
     *
     * @param string $name The name of the attribute
     *
     * @return Boolean
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Returns the value of the attributes with the given name.
     *
     * @param string $name The name of the attribute
     */
    public function getAttribute($name)
    {
        return $this->attributes[$name];
    }

    /**
     * Updates the form with default data.
     *
     * @param array $appData The data formatted as expected for the underlying object
     *
     * @return Form The current form
     */
    public function setData($appData)
    {
        if ($this->bound) {
            throw new AlreadyBoundException('You cannot change the data of a bound form');
        }

        $event = new DataEvent($this, $appData);
        $this->dispatcher->dispatch(FormEvents::PRE_SET_DATA, $event);

        // Hook to change content of the data
        $event = new FilterDataEvent($this, $appData);
        $this->dispatcher->dispatch(FormEvents::SET_DATA, $event);
        $appData = $event->getData();

        // Treat data as strings unless a value transformer exists
        if (!$this->clientTransformers && !$this->normTransformers && is_scalar($appData)) {
            $appData = (string) $appData;
        }

        // Synchronize representations - must not change the content!
        $normData = $this->appToNorm($appData);
        $clientData = $this->normToClient($normData);

        $this->appData = $appData;
        $this->normData = $normData;
        $this->clientData = $clientData;
        $this->synchronized = true;

        if (count($this->children) > 0 && $this->dataMapper) {
            // Update child forms from the data
            $this->dataMapper->mapDataToForms($clientData, $this->children);
        }

        $event = new DataEvent($this, $appData);
        $this->dispatcher->dispatch(FormEvents::POST_SET_DATA, $event);

        return $this;
    }

    /**
     * Returns the data in the format needed for the underlying object.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->appData;
    }

    /**
     * Returns the data transformed by the value transformer.
     *
     * @return string
     */
    public function getClientData()
    {
        return $this->clientData;
    }

    /**
     * Returns the extra data.
     *
     * @return array The bound data which do not belong to a child
     */
    public function getExtraData()
    {
        return $this->extraData;
    }

    /**
     * Binds data to the form, transforms and validates it.
     *
     * @param string|array $clientData The data
     *
     * @return Form The current form
     *
     * @throws UnexpectedTypeException
     */
    public function bind($clientData)
    {
        if ($this->bound) {
            throw new AlreadyBoundException('A form can only be bound once');
        }

        if ($this->isDisabled()) {
            $this->bound = true;

            return $this;
        }

        // Don't convert NULL to a string here in order to determine later
        // whether an empty value has been submitted or whether no value has
        // been submitted at all. This is important for processing checkboxes
        // and radio buttons with empty values.
        if (is_scalar($clientData)) {
            $clientData = (string) $clientData;
        }

        // Initialize errors in the very beginning so that we don't lose any
        // errors added during listeners
        $this->errors = array();

        $event = new DataEvent($this, $clientData);
        $this->dispatcher->dispatch(FormEvents::PRE_BIND, $event);

        $appData = null;
        $normData = null;
        $extraData = array();
        $synchronized = false;

        // Hook to change content of the data bound by the browser
        $event = new FilterDataEvent($this, $clientData);
        $this->dispatcher->dispatch(FormEvents::BIND_CLIENT_DATA, $event);
        $clientData = $event->getData();

        if (count($this->children) > 0) {
            if (null === $clientData || '' === $clientData) {
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

            // If we have a data mapper, use old client data and merge
            // data from the children into it later
            if ($this->dataMapper) {
                $clientData = $this->getClientData();
            }
        }

        if (null === $clientData || '' === $clientData) {
            $emptyData = $this->emptyData;

            if ($emptyData instanceof \Closure) {
                $emptyData = $emptyData($this, $clientData);
            }

            $clientData = $emptyData;
        }

        // Merge form data from children into existing client data
        if (count($this->children) > 0 && $this->dataMapper && null !== $clientData) {
            $this->dataMapper->mapFormsToData($this->children, $clientData);
        }

        try {
            // Normalize data to unified representation
            $normData = $this->clientToNorm($clientData);
            $synchronized = true;
        } catch (TransformationFailedException $e) {
        }

        if ($synchronized) {
            // Hook to change content of the data into the normalized
            // representation
            $event = new FilterDataEvent($this, $normData);
            $this->dispatcher->dispatch(FormEvents::BIND_NORM_DATA, $event);
            $normData = $event->getData();

            // Synchronize representations - must not change the content!
            $appData = $this->normToApp($normData);
            $clientData = $this->normToClient($normData);
        }

        $this->bound = true;
        $this->appData = $appData;
        $this->normData = $normData;
        $this->clientData = $clientData;
        $this->extraData = $extraData;
        $this->synchronized = $synchronized;

        $event = new DataEvent($this, $clientData);
        $this->dispatcher->dispatch(FormEvents::POST_BIND, $event);

        foreach ($this->validators as $validator) {
            $validator->validate($this);
        }

        return $this;
    }

    /**
     * Binds a request to the form.
     *
     * If the request method is POST, PUT or GET, the data is bound to the form,
     * transformed and written into the form data (an object or an array).
     *
     * @param Request $request    The request to bind to the form
     *
     * @return Form This form
     *
     * @throws FormException if the method of the request is not one of GET, POST or PUT
     */
    public function bindRequest(Request $request)
    {
        // Store the bound data in case of a post request
        switch ($request->getMethod()) {
            case 'POST':
            case 'PUT':
            case 'DELETE':
            case 'PATCH':
                if ('' === $this->getName()) {
                    // Form bound without name
                    $params = $request->request->all();
                    $files = $request->files->all();
                } elseif ($this->hasChildren()) {
                    // Form bound with name and children
                    $params = $request->request->get($this->getName(), array());
                    $files = $request->files->get($this->getName(), array());
                } else {
                    // Form bound with name, but without children
                    $params = $request->request->get($this->getName(), null);
                    $files = $request->files->get($this->getName(), null);
                }
                if (is_array($params) && is_array($files)) {
                    $data = array_replace_recursive($params, $files);
                } else {
                    $data = $params ?: $files;
                }
                break;
            case 'GET':
                $data = '' === $this->getName() ? $request->query->all() : $request->query->get($this->getName(), array());
                break;
            default:
                throw new FormException(sprintf('The request method "%s" is not supported', $request->getMethod()));
        }

        return $this->bind($data);
    }

    /**
     * Returns the normalized data of the form.
     *
     * @return mixed  When the form is not bound, the default data is returned.
     *                When the form is bound, the normalized bound data is
     *                returned if the form is valid, null otherwise.
     */
    public function getNormData()
    {
        return $this->normData;
    }

    /**
     * Adds an error to this form.
     *
     * @param FormError $error
     *
     * @return Form The current form
     */
    public function addError(FormError $error)
    {
        if ($this->parent && $this->getErrorBubbling()) {
            $this->parent->addError($error);
        } else {
            $this->errors[] = $error;
        }

        return $this;
    }

    /**
     * Returns whether errors bubble up to the parent.
     *
     * @return Boolean
     */
    public function getErrorBubbling()
    {
        return $this->errorBubbling;
    }

    /**
     * Returns whether the form is bound.
     *
     * @return Boolean true if the form is bound to input values, false otherwise
     */
    public function isBound()
    {
        return $this->bound;
    }

    /**
     * Returns whether the data in the different formats is synchronized.
     *
     * @return Boolean
     */
    public function isSynchronized()
    {
        return $this->synchronized;
    }

    /**
     * Returns whether the form is empty.
     *
     * @return Boolean
     */
    public function isEmpty()
    {
        foreach ($this->children as $child) {
            if (!$child->isEmpty()) {
                return false;
            }
        }

        return array() === $this->appData || null === $this->appData || '' === $this->appData;
    }

    /**
     * Returns whether the form is valid.
     *
     * @return Boolean
     */
    public function isValid()
    {
        if (!$this->bound) {
            throw new \LogicException('You cannot call isValid() on a form that is not bound.');
        }

        if ($this->hasErrors()) {
            return false;
        }

        if (!$this->isDisabled()) {
            foreach ($this->children as $child) {
                if (!$child->isValid()) {
                    return false;
                }
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
        // Forms are not valid if their children are invalid, but
        // hasErrors() returns only true if a form itself has errors
        return count($this->errors) > 0;
    }

    /**
     * Returns all errors.
     *
     * @return array  An array of FormError instances that occurred during binding
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Returns a string representation of all form errors (including children errors).
     *
     * This method should only be used to help debug a form.
     *
     * @param integer $level The indentation level (used internally)
     *
     * @return string A string representation of all errors
     */
    public function getErrorsAsString($level = 0)
    {
        $errors = '';
        foreach ($this->errors as $error) {
            $errors .= str_repeat(' ', $level).'ERROR: '.$error->getMessage()."\n";
        }

        if ($this->hasChildren()) {
            foreach ($this->children as $key => $child) {
                $errors .= str_repeat(' ', $level).$key.":\n";
                if ($err = $child->getErrorsAsString($level + 4)) {
                    $errors .= $err;
                } else {
                    $errors .= str_repeat(' ', $level + 4)."No errors\n";
                }
            }
        }

        return $errors;
    }

    /**
     * Returns the DataTransformers.
     *
     * @return array An array of DataTransformerInterface
     */
    public function getNormTransformers()
    {
        return $this->normTransformers;
    }

    /**
     * Returns the DataTransformers.
     *
     * @return array An array of DataTransformerInterface
     */
    public function getClientTransformers()
    {
        return $this->clientTransformers;
    }

    /**
     * Returns all children in this group.
     *
     * @return array
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Returns whether the form has children.
     *
     * @return Boolean
     */
    public function hasChildren()
    {
        return count($this->children) > 0;
    }

    /**
     * Adds a child to the form.
     *
     * @param FormInterface $child The FormInterface to add as a child
     *
     * @return Form the current form
     */
    public function add(FormInterface $child)
    {
        if ($this->bound) {
            throw new AlreadyBoundException('You cannot add children to a bound form');
        }

        $this->children[$child->getName()] = $child;

        $child->setParent($this);

        if ($this->dataMapper) {
            $this->dataMapper->mapDataToForm($this->getClientData(), $child);
        }

        return $this;
    }

    /**
     * Removes a child from the form.
     *
     * @param string $name The name of the child to remove
     *
     * @return Form the current form
     */
    public function remove($name)
    {
        if ($this->bound) {
            throw new AlreadyBoundException('You cannot remove children from a bound form');
        }

        if (isset($this->children[$name])) {
            $this->children[$name]->setParent(null);

            unset($this->children[$name]);
        }

        return $this;
    }

    /**
     * Returns whether a child with the given name exists.
     *
     * @param  string $name
     *
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
     *
     * @return FormInterface
     *
     * @throws \InvalidArgumentException if the child does not exist
     */
    public function get($name)
    {
        if (isset($this->children[$name])) {
            return $this->children[$name];
        }

        throw new \InvalidArgumentException(sprintf('Child "%s" does not exist.', $name));
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
     * @return FormInterface  A form instance
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * Adds a child to the form (implements the \ArrayAccess interface).
     *
     * @param string $name Ignored. The name of the child is used.
     * @param FormInterface $child  The child to be added
     */
    public function offsetSet($name, $child)
    {
        $this->add($child);
    }

    /**
     * Removes the child with the given name from the form (implements the \ArrayAccess interface).
     *
     * @param string $name  The name of the child to be removed
     */
    public function offsetUnset($name)
    {
        $this->remove($name);
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
     * Creates a view.
     *
     * @param FormView $parent The parent view
     *
     * @return FormView The view
     */
    public function createView(FormView $parent = null)
    {
        if (null === $parent && $this->parent) {
            $parent = $this->parent->createView();
        }

        $view = new FormView($this->name);

        $view->setParent($parent);

        $types = (array) $this->types;

        foreach ($types as $type) {
            $type->buildView($view, $this);

            foreach ($type->getExtensions() as $typeExtension) {
                $typeExtension->buildView($view, $this);
            }
        }

        foreach ($this->children as $child) {
            $view->addChild($child->createView($view));
        }

        foreach ($types as $type) {
            $type->buildViewBottomUp($view, $this);

            foreach ($type->getExtensions() as $typeExtension) {
                $typeExtension->buildViewBottomUp($view, $this);
            }
        }

        return $view;
    }

    /**
     * Normalizes the value if a normalization transformer is set.
     *
     * @param  mixed $value  The value to transform
     *
     * @return string
     */
    private function appToNorm($value)
    {
        foreach ($this->normTransformers as $transformer) {
            $value = $transformer->transform($value);
        }

        return $value;
    }

    /**
     * Reverse transforms a value if a normalization transformer is set.
     *
     * @param  string $value  The value to reverse transform
     *
     * @return mixed
     */
    private function normToApp($value)
    {
        for ($i = count($this->normTransformers) - 1; $i >= 0; --$i) {
            $value = $this->normTransformers[$i]->reverseTransform($value);
        }

        return $value;
    }

    /**
     * Transforms the value if a value transformer is set.
     *
     * @param  mixed $value  The value to transform
     *
     * @return string
     */
    private function normToClient($value)
    {
        if (!$this->clientTransformers) {
            // Scalar values should always be converted to strings to
            // facilitate differentiation between empty ("") and zero (0).
            return null === $value || is_scalar($value) ? (string) $value : $value;
        }

        foreach ($this->clientTransformers as $transformer) {
            $value = $transformer->transform($value);
        }

        return $value;
    }

    /**
     * Reverse transforms a value if a value transformer is set.
     *
     * @param  string $value  The value to reverse transform
     *
     * @return mixed
     */
    private function clientToNorm($value)
    {
        if (!$this->clientTransformers) {
            return '' === $value ? null : $value;
        }

        for ($i = count($this->clientTransformers) - 1; $i >= 0; --$i) {
            $value = $this->clientTransformers[$i]->reverseTransform($value);
        }

        return $value;
    }

    /**
     * Validates whether the given variable is a valid form name.
     *
     * @param string $name The tested form name.
     *
     * @throws UnexpectedTypeException If the name is not a string.
     * @throws \InvalidArgumentException If the name contains invalid characters.
     */
    static public function validateName($name)
    {
        if (!is_string($name)) {
            throw new UnexpectedTypeException($name, 'string');
        }

        if (!self::isValidName($name)) {
            throw new \InvalidArgumentException(sprintf(
                'The name "%s" contains illegal characters. Names should start with a letter, digit or underscore and only contain letters, digits, numbers, underscores ("_"), hyphens ("-") and colons (":").',
                $name
            ));
        }
    }

    /**
     * Returns whether the given variable contains a valid form name.
     *
     * A name is accepted if it
     *
     *   * is empty
     *   * starts with a letter, digit or underscore
     *   * contains only letters, digits, numbers, underscores ("_"),
     *     hyphens ("-") and colons (":")
     *
     * @param string $name The tested form name.
     *
     * @return Boolean Whether the name is valid.
     */
    static public function isValidName($name)
    {
        return '' === $name || preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_\-:]*$/D', $name);
    }
}
