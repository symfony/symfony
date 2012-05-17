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

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A basic form configuration.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormConfig implements FormConfigInterface
{
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var string
     */
    private $name;

    /**
     * @var PropertyPath
     */
    private $propertyPath;

    /**
     * @var Boolean
     */
    private $mapped;

    /**
     * @var array
     */
    private $types = array();

    /**
     * @var array
     */
    private $clientTransformers = array();

    /**
     * @var array
     */
    private $normTransformers = array();

    /**
     * @var DataMapperInterface
     */
    private $dataMapper;

    /**
     * @var FormValidatorInterface
     */
    private $validators = array();

    /**
     * @var Boolean
     */
    private $required;

    /**
     * @var Boolean
     */
    private $disabled;

    /**
     * @var Boolean
     */
    private $errorBubbling;

    /**
     * @var mixed
     */
    private $emptyData;

    /**
     * @var array
     */
    private $attributes = array();

    /**
     * @var mixed
     */
    private $data;

    /**
     * @var string
     */
    private $dataClass;

    /**
     * Creates an empty form configuration.
     *
     * @param  string                   $name       The form name.
     * @param  string                   $dataClass  The class of the form's data.
     * @param  EventDispatcherInterface $dispatcher The event dispatcher.
     *
     * @throws UnexpectedTypeException   If the name is not a string.
     * @throws \InvalidArgumentException If the data class is not a valid class or if
     *                                   the name contains invalid characters.
     */
    public function __construct($name, $dataClass, EventDispatcherInterface $dispatcher)
    {
        $name = (string) $name;

        self::validateName($name);

        if (null !== $dataClass && !class_exists($dataClass)) {
            throw new \InvalidArgumentException(sprintf('The data class "%s" is not a valid class.', $dataClass));
        }

        $this->name = $name;
        $this->dataClass = $dataClass;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Adds an event listener to an event on this form.
     *
     * @param string   $eventName The name of the event to listen to.
     * @param callable $listener  The listener to execute.
     * @param integer  $priority  The priority of the listener. Listeners
     *                            with a higher priority are called before
     *                            listeners with a lower priority.
     *
     * @return self The configuration object.
     */
    public function addEventListener($eventName, $listener, $priority = 0)
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);

        return $this;
    }

    /**
     * Adds an event subscriber for events on this form.
     *
     * @param EventSubscriberInterface $subscriber The subscriber to attach.
     *
     * @return self The configuration object.
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->dispatcher->addSubscriber($subscriber);

        return $this;
    }

    /**
     * Adds a validator to the form.
     *
     * @param FormValidatorInterface $validator The validator.
     *
     * @return self The configuration object.
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function addValidator(FormValidatorInterface $validator)
    {
        $this->validators[] = $validator;

        return $this;
    }

    /**
     * Appends a transformer to the client transformer chain
     *
     * @param DataTransformerInterface $clientTransformer
     *
     * @return self The configuration object.
     */
    public function appendClientTransformer(DataTransformerInterface $clientTransformer)
    {
        $this->clientTransformers[] = $clientTransformer;

        return $this;
    }

    /**
     * Prepends a transformer to the client transformer chain.
     *
     * @param DataTransformerInterface $clientTransformer
     *
     * @return self The configuration object.
     */
    public function prependClientTransformer(DataTransformerInterface $clientTransformer)
    {
        array_unshift($this->clientTransformers, $clientTransformer);

        return $this;
    }

    /**
     * Clears the client transformers.
     *
     * @return self The configuration object.
     */
    public function resetClientTransformers()
    {
        $this->clientTransformers = array();

        return $this;
    }

    /**
     * Appends a transformer to the normalization transformer chain
     *
     * @param DataTransformerInterface $normTransformer
     *
     * @return self The configuration object.
     */
    public function appendNormTransformer(DataTransformerInterface $normTransformer)
    {
        $this->normTransformers[] = $normTransformer;

        return $this;
    }

    /**
     * Prepends a transformer to the normalization transformer chain
     *
     * @param DataTransformerInterface $normTransformer
     *
     * @return self The configuration object.
     */
    public function prependNormTransformer(DataTransformerInterface $normTransformer)
    {
        array_unshift($this->normTransformers, $normTransformer);

        return $this;
    }

    /**
     * Clears the normalization transformers.
     *
     * @return self The configuration object.
     */
    public function resetNormTransformers()
    {
        $this->normTransformers = array();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyPath()
    {
        return $this->propertyPath;
    }

    /**
     * {@inheritdoc}
     */
    public function getMapped()
    {
        return $this->mapped;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientTransformers()
    {
        return $this->clientTransformers;
    }

    /**
     * {@inheritdoc}
     */
    public function getNormTransformers()
    {
        return $this->normTransformers;
    }

    /**
     * Returns the data mapper of the form.
     *
     * @return DataMapperInterface The data mapper.
     */
    public function getDataMapper()
    {
        return $this->dataMapper;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidators()
    {
        return $this->validators;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequired()
    {
        return $this->required;
    }

    /**
     * {@inheritdoc}
     */
    public function getDisabled()
    {
        return $this->disabled;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorBubbling()
    {
        return $this->errorBubbling;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmptyData()
    {
        return $this->emptyData;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * {@inheritdoc}
     */
    function getAttribute($name)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataClass()
    {
        return $this->dataClass;
    }

    /**
     * Sets the value for an attribute.
     *
     * @param string $name  The name of the attribute
     * @param string $value The value of the attribute
     *
     * @return self The configuration object.
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Sets the attributes.
     *
     * @param array $attributes The attributes.
     *
     * @return self The configuration object.
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Sets the data mapper used by the form.
     *
     * @param  DataMapperInterface $dataMapper
     *
     * @return self The configuration object.
     */
    public function setDataMapper(DataMapperInterface $dataMapper = null)
    {
        $this->dataMapper = $dataMapper;

        return $this;
    }

    /**
     * Set whether the form is disabled.
     *
     * @param  Boolean $disabled Whether the form is disabled
     *
     * @return self The configuration object.
     */
    public function setDisabled($disabled)
    {
        $this->disabled = (Boolean) $disabled;

        return $this;
    }

    /**
     * Sets the data used for the client data when no value is bound.
     *
     * @param  mixed $emptyData The empty data.
     *
     * @return self The configuration object.
     */
    public function setEmptyData($emptyData)
    {
        $this->emptyData = $emptyData;

        return $this;
    }

    /**
     * Sets whether errors bubble up to the parent.
     *
     * @param  Boolean $errorBubbling
     *
     * @return self The configuration object.
     */
    public function setErrorBubbling($errorBubbling)
    {
        $this->errorBubbling = null === $errorBubbling ? null : (Boolean) $errorBubbling;

        return $this;
    }

    /**
     * Sets whether this field is required to be filled out when bound.
     *
     * @param Boolean $required
     *
     * @return self The configuration object.
     */
    public function setRequired($required)
    {
        $this->required = (Boolean) $required;

        return $this;
    }

    /**
     * Sets the property path that the form should be mapped to.
     *
     * @param  string|PropertyPath $propertyPath The property path or null if the path
     *                                           should be set automatically based on
     *                                           the form's name.
     *
     * @return self The configuration object.
     */
    public function setPropertyPath($propertyPath)
    {
        if (null !== $propertyPath && !$propertyPath instanceof PropertyPath) {
            $propertyPath = new PropertyPath($propertyPath);
        }

        $this->propertyPath = $propertyPath;

        return $this;
    }

    /**
     * Sets whether the form should be mapped to an element of its
     * parent's data.
     *
     * @param  Boolean $mapped Whether the form should be mapped.
     *
     * @return self The configuration object.
     */
    public function setMapped($mapped)
    {
        $this->mapped = $mapped;

        return $this;
    }

    /**
     * Set the types.
     *
     * @param array $types An array FormTypeInterface
     *
     * @return self The configuration object.
     */
    public function setTypes(array $types)
    {
        $this->types = $types;

        return $this;
    }

    /**
     * Sets the initial data of the form.
     *
     * @param array $data The data of the form in application format.
     *
     * @return self The configuration object.
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Validates whether the given variable is a valid form name.
     *
     * @param string $name The tested form name.
     *
     * @throws UnexpectedTypeException   If the name is not a string.
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
