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
class FormConfig implements FormConfigEditorInterface
{
    /**
     * @var EventDispatcherInterface
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
    private $mapped = true;

    /**
     * @var Boolean
     */
    private $byReference = true;

    /**
     * @var Boolean
     */
    private $virtual = false;

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
     * @var array
     */
    private $validators = array();

    /**
     * @var Boolean
     */
    private $required = true;

    /**
     * @var Boolean
     */
    private $disabled = false;

    /**
     * @var Boolean
     */
    private $errorBubbling = false;

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
     * @var array
     */
    private $options;

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
    public function __construct($name, $dataClass, EventDispatcherInterface $dispatcher, array $options = array())
    {
        $name = (string) $name;

        self::validateName($name);

        if (null !== $dataClass && !class_exists($dataClass)) {
            throw new \InvalidArgumentException(sprintf('The data class "%s" is not a valid class.', $dataClass));
        }

        $this->name = $name;
        $this->dataClass = $dataClass;
        $this->dispatcher = $dispatcher;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function addEventListener($eventName, $listener, $priority = 0)
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->dispatcher->addSubscriber($subscriber);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addValidator(FormValidatorInterface $validator)
    {
        $this->validators[] = $validator;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function appendClientTransformer(DataTransformerInterface $clientTransformer)
    {
        $this->clientTransformers[] = $clientTransformer;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prependClientTransformer(DataTransformerInterface $clientTransformer)
    {
        array_unshift($this->clientTransformers, $clientTransformer);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resetClientTransformers()
    {
        $this->clientTransformers = array();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function appendNormTransformer(DataTransformerInterface $normTransformer)
    {
        $this->normTransformers[] = $normTransformer;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prependNormTransformer(DataTransformerInterface $normTransformer)
    {
        array_unshift($this->normTransformers, $normTransformer);

        return $this;
    }

    /**
     * {@inheritdoc}
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
    public function getByReference()
    {
        return $this->byReference;
    }

    /**
     * {@inheritdoc}
     */
    public function getVirtual()
    {
        return $this->virtual;
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
     * {@inheritdoc}
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
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($name, $default = null)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : $default;
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
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDataMapper(DataMapperInterface $dataMapper = null)
    {
        $this->dataMapper = $dataMapper;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDisabled($disabled)
    {
        $this->disabled = (Boolean) $disabled;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setEmptyData($emptyData)
    {
        $this->emptyData = $emptyData;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setErrorBubbling($errorBubbling)
    {
        $this->errorBubbling = null === $errorBubbling ? null : (Boolean) $errorBubbling;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequired($required)
    {
        $this->required = (Boolean) $required;

        return $this;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function setMapped($mapped)
    {
        $this->mapped = $mapped;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setByReference($byReference)
    {
        $this->byReference = $byReference;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setVirtual($virtual)
    {
        $this->virtual = $virtual;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTypes(array $types)
    {
        $this->types = $types;

        return $this;
    }

    /**
     * {@inheritdoc}
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
