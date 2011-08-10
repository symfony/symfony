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

use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\CircularReferenceException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormBuilder
{
    /**
     * @var string
     */
    private $name;

    /**
     * The form data in application format
     * @var mixed
     */
    private $appData;

    /**
     * The event dispatcher
     *
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * The form factory
     * @var FormFactoryInterface
     */
    private $factory;

    /**
     * @var Boolean
     */
    private $readOnly;

    /**
     * @var Boolean
     */
    private $required;

    /**
     * The transformers for transforming from normalized to client format and
     * back
     * @var array An array of DataTransformerInterface
     */
    private $clientTransformers = array();

    /**
     * The transformers for transforming from application to normalized format
     * and back
     * @var array An array of DataTransformerInterface
     */
    private $normTransformers = array();

    /**
     * @var array An array of FormValidatorInterface
     */
    private $validators = array();

    /**
     * Key-value store for arbitrary attributes attached to the form
     * @var array
     */
    private $attributes = array();

    /**
     * @var array An array of FormTypeInterface
     */
    private $types = array();

    /**
     * @var string
     */
    private $dataClass;

    /**
     * The children of the form
     * @var array
     */
    private $children = array();

    /**
     * @var DataMapperInterface
     */
    private $dataMapper;

    /**
     * Whether added errors should bubble up to the parent
     * @var Boolean
     */
    private $errorBubbling = false;

    /**
     * Data used for the client data when no value is bound
     * @var mixed
     */
    private $emptyData = '';

    private $currentLoadingType;

    /**
     * Constructor.
     *
     * @param string                    $name
     * @param FormFactoryInterface      $factory
     * @param EventDispatcherInterface  $dispatcher
     * @param string                    $dataClass
     */
    public function __construct($name, FormFactoryInterface $factory, EventDispatcherInterface $dispatcher, $dataClass = null)
    {
        $this->name = $name;
        $this->factory = $factory;
        $this->dispatcher = $dispatcher;
        $this->dataClass = $dataClass;
    }

    /**
     * Returns the associated form factory.
     *
     * @return FormFactoryInterface The factory
     */
    public function getFormFactory()
    {
        return $this->factory;
    }

    /**
     * Returns the name of the form.
     *
     * @return string The form name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Updates the field with default data.
     *
     * @param array $appData The data formatted as expected for the underlying object
     *
     * @return FormBuilder The current builder
     */
    public function setData($appData)
    {
        $this->appData = $appData;

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
     * Set whether the form is read only
     *
     * @param Boolean $readOnly Whether the form is read only
     *
     * @return FormBuilder The current builder
     */
    public function setReadOnly($readOnly)
    {
        $this->readOnly = (Boolean) $readOnly;

        return $this;
    }

    /**
     * Returns whether the form is read only.
     *
     * @return Boolean Whether the form is read only
     */
    public function getReadOnly()
    {
        return $this->readOnly;
    }

    /**
     * Sets whether this field is required to be filled out when bound.
     *
     * @param Boolean $required
     *
     * @return FormBuilder The current builder
     */
    public function setRequired($required)
    {
        $this->required = (Boolean) $required;

        return $this;
    }

    /**
     * Returns whether this field is required to be filled out when bound.
     *
     * @return Boolean Whether this field is required
     */
    public function getRequired()
    {
        return $this->required;
    }

    /**
     * Sets whether errors bubble up to the parent.
     *
     * @param type $errorBubbling
     *
     * @return FormBuilder The current builder
     */
    public function setErrorBubbling($errorBubbling)
    {
        $this->errorBubbling = (Boolean) $errorBubbling;

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
     * Adds a validator to the form.
     *
     * @param FormValidatorInterface $validator The validator
     *
     * @return FormBuilder The current builder
     */
    public function addValidator(FormValidatorInterface $validator)
    {
        $this->validators[] = $validator;

        return $this;
    }

    /**
     * Returns the validators used by the form.
     *
     * @return array An array of FormValidatorInterface
     */
    public function getValidators()
    {
        return $this->validators;
    }

    /**
     * Adds an event listener for events on this field
     *
     * @see Symfony\Component\EventDispatcher\EventDispatcherInterface::addListener
     *
     * @return FormBuilder The current builder
     */
    public function addEventListener($eventName, $listener, $priority = 0)
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);

        return $this;
    }

    /**
     * Adds an event subscriber for events on this field
     *
     * @see Symfony\Component\EventDispatcher\EventDispatcherInterface::addSubscriber
     *
     * @return FormBuilder The current builder
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->dispatcher->addSubscriber($subscriber);

        return $this;
    }

    /**
     * Appends a transformer to the normalization transformer chain
     *
     * @param DataTransformerInterface $clientTransformer
     *
     * @return FormBuilder The current builder
     */
    public function appendNormTransformer(DataTransformerInterface $normTransformer)
    {
        $this->normTransformers[] = $normTransformer;

        return $this;
    }

    /**
     * Prepends a transformer to the client transformer chain
     *
     * @param DataTransformerInterface $normTransformer
     *
     * @return FormBuilder The current builder
     */
    public function prependNormTransformer(DataTransformerInterface $normTransformer)
    {
        array_unshift($this->normTransformers, $normTransformer);

        return $this;
    }

    /**
     * Clears the normalization transformers.
     *
     * @return FormBuilder The current builder
     */
    public function resetNormTransformers()
    {
        $this->normTransformers = array();

        return $this;
    }

    /**
     * Returns all the normalization transformers.
     *
     * @return array An array of DataTransformerInterface
     */
    public function getNormTransformers()
    {
        return $this->normTransformers;
    }

    /**
     * Appends a transformer to the client transformer chain
     *
     * @param DataTransformerInterface $clientTransformer
     *
     * @return FormBuilder The current builder
     */
    public function appendClientTransformer(DataTransformerInterface $clientTransformer)
    {
        $this->clientTransformers[] = $clientTransformer;

        return $this;
    }

    /**
     * Prepends a transformer to the client transformer chain
     *
     * @param DataTransformerInterface $clientTransformer
     *
     * @return FormBuilder The current builder
     */
    public function prependClientTransformer(DataTransformerInterface $clientTransformer)
    {
        array_unshift($this->clientTransformers, $clientTransformer);

        return $this;
    }

    /**
     * Clears the client transformers.
     *
     * @return FormBuilder The current builder
     */
    public function resetClientTransformers()
    {
        $this->clientTransformers = array();

        return $this;
    }

    /**
     * Returns all the client transformers.
     *
     * @return array An array of DataTransformerInterface
     */
    public function getClientTransformers()
    {
        return $this->clientTransformers;
    }

    /**
     * Sets the value for an attribute.
     *
     * @param string $name  The name of the attribute
     * @param string $value The value of the attribute
     *
     * @return FormBuilder The current builder
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;

        return $this;
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
     * Returns whether the form has an attribute with the given name.
     *
     * @param string $name The name of the attribute
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Returns all the attributes.
     *
     * @return array An array of attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Sets the data mapper used by the form.
     *
     * @param DataMapperInterface $dataMapper
     *
     * @return FormBuilder The current builder
     */
    public function setDataMapper(DataMapperInterface $dataMapper)
    {
        $this->dataMapper = $dataMapper;

        return $this;
    }

    /**
     * Returns the data mapper used by the form.
     *
     * @return array An array of DataMapperInterface
     */
    public function getDataMapper()
    {
        return $this->dataMapper;
    }

    /**
     * Set the types.
     *
     * @param array $types An array FormTypeInterface
     *
     * @return FormBuilder The current builder
     */
    public function setTypes(array $types)
    {
        $this->types = $types;

        return $this;
    }

    /**
     * Return the types.
     *
     * @return array An array of FormTypeInterface
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * Sets the data used for the client data when no value is bound.
     *
     * @param mixed $emptyData
     */
    public function setEmptyData($emptyData)
    {
        $this->emptyData = $emptyData;

        return $this;
    }

    /**
     * Returns the data used for the client data when no value is bound.
     *
     * @return mixed
     */
    public function getEmptyData()
    {
        return $this->emptyData;
    }

    /**
     * Adds a new field to this group. A field must have a unique name within
     * the group. Otherwise the existing field is overwritten.
     *
     * If you add a nested group, this group should also be represented in the
     * object hierarchy.
     *
     * @param string|FormBuilder       $child
     * @param string|FormTypeInterface $type
     * @param array                    $options
     *
     * @return FormBuilder The current builder
     */
    public function add($child, $type = null, array $options = array())
    {
        if ($child instanceof self) {
            $this->children[$child->getName()] = $child;

            return $this;
        }

        if (!is_string($child)) {
            throw new UnexpectedTypeException($child, 'string or Symfony\Component\Form\FormBuilder');
        }

        if (null !== $type && !is_string($type) && !$type instanceof FormTypeInterface) {
            throw new UnexpectedTypeException($type, 'string or Symfony\Component\Form\FormTypeInterface');
        }

        if ($this->currentLoadingType && ($type instanceof FormTypeInterface ? $type->getName() : $type) == $this->currentLoadingType) {
            throw new CircularReferenceException(is_string($type) ? $this->getFormFactory()->getType($type) : $type);
        }

        $this->children[$child] = array(
            'type'      => $type,
            'options'   => $options,
        );

        return $this;
    }

    /**
     * Creates a form builder.
     *
     * @param string                    $name    The name of the form or the name of the property
     * @param string|FormTypeInterface  $type    The type of the form or null if name is a property
     * @param array                     $options The options
     *
     * @return FormBuilder The builder
     */
    public function create($name, $type = null, array $options = array())
    {
        if (null === $type && !$this->dataClass) {
            $type = 'text';
        }

        if (null !== $type) {
            return $this->getFormFactory()->createNamedBuilder($type, $name, null, $options);
        }

        return $this->getFormFactory()->createBuilderForProperty($this->dataClass, $name, null, $options);
    }

    /**
     * Returns a child by name.
     *
     * @param string $name The name of the child
     *
     * @return FormBuilder The builder for the child
     *
     * @throws FormException if the given child does not exist
     */
    public function get($name)
    {
        if (!isset($this->children[$name])) {
            throw new FormException(sprintf('The field "%s" does not exist', $name));
        }

        if (!$this->children[$name] instanceof FormBuilder) {
            $this->children[$name] = $this->create(
                $name,
                $this->children[$name]['type'],
                $this->children[$name]['options']
            );
        }

        return $this->children[$name];
    }

    /**
     * Removes the field with the given name.
     *
     * @param string $name
     *
     * @return FormBuilder The current builder
     */
    public function remove($name)
    {
        if (isset($this->children[$name])) {
            unset($this->children[$name]);
        }

        return $this;
    }

    /**
     * Returns whether a field with the given name exists.
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
     * Creates the form.
     *
     * @return Form The form
     */
    public function getForm()
    {
        $instance = new Form(
            $this->getName(),
            $this->buildDispatcher(),
            $this->getTypes(),
            $this->getClientTransformers(),
            $this->getNormTransformers(),
            $this->getDataMapper(),
            $this->getValidators(),
            $this->getRequired(),
            $this->getReadOnly(),
            $this->getErrorBubbling(),
            $this->getEmptyData(),
            $this->getAttributes()
        );

        foreach ($this->buildChildren() as $child) {
            $instance->add($child);
        }

        if ($this->getData()) {
            $instance->setData($this->getData());
        }

        return $instance;
    }

    public function setCurrentLoadingType($type)
    {
        $this->currentLoadingType = $type;
    }

    /**
     * Returns the event dispatcher.
     *
     * @return type
     */
    protected function buildDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Creates the children.
     *
     * @return array An array of Form
     */
    protected function buildChildren()
    {
        $children = array();

        foreach ($this->children as $name => $builder) {
            if (!$builder instanceof FormBuilder) {
                $builder = $this->create($name, $builder['type'], $builder['options']);
            }

            $children[$builder->getName()] = $builder->getForm();
        }

        return $children;
    }
}
