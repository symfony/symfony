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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\ImmutableEventDispatcher;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * A basic form configuration.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormConfigBuilder implements FormConfigBuilderInterface
{
    /**
     * Caches a globally unique {@link NativeRequestHandler} instance.
     *
     * @var NativeRequestHandler
     */
    private static $nativeRequestHandler;

    /**
     * @var bool
     */
    protected $locked = false;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var string
     */
    private $name;

    /**
     * @var PropertyPathInterface
     */
    private $propertyPath;

    /**
     * @var bool
     */
    private $mapped = true;

    /**
     * @var bool
     */
    private $byReference = true;

    /**
     * @var bool
     */
    private $inheritData = false;

    /**
     * @var bool
     */
    private $compound = false;

    /**
     * @var ResolvedFormTypeInterface
     */
    private $type;

    /**
     * @var array
     */
    private $viewTransformers = [];

    /**
     * @var array
     */
    private $modelTransformers = [];

    /**
     * @var DataMapperInterface
     */
    private $dataMapper;

    /**
     * @var bool
     */
    private $required = true;

    /**
     * @var bool
     */
    private $disabled = false;

    /**
     * @var bool
     */
    private $errorBubbling = false;

    /**
     * @var mixed
     */
    private $emptyData;

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var mixed
     */
    private $data;

    /**
     * @var string|null
     */
    private $dataClass;

    /**
     * @var bool
     */
    private $dataLocked;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var string
     */
    private $action;

    /**
     * @var string
     */
    private $method = 'POST';

    /**
     * @var RequestHandlerInterface
     */
    private $requestHandler;

    /**
     * @var bool
     */
    private $autoInitialize = false;

    /**
     * @var array
     */
    private $options;

    /**
     * Creates an empty form configuration.
     *
     * @param string|int               $name       The form name
     * @param string|null              $dataClass  The class of the form's data
     * @param EventDispatcherInterface $dispatcher The event dispatcher
     * @param array                    $options    The form options
     *
     * @throws InvalidArgumentException if the data class is not a valid class or if
     *                                  the name contains invalid characters
     */
    public function __construct($name, ?string $dataClass, EventDispatcherInterface $dispatcher, array $options = [])
    {
        self::validateName($name);

        if (null !== $dataClass && !class_exists($dataClass) && !interface_exists($dataClass, false)) {
            throw new InvalidArgumentException(sprintf('Class "%s" not found. Is the "data_class" form option set correctly?', $dataClass));
        }

        $this->name = (string) $name;
        $this->dataClass = $dataClass;
        $this->dispatcher = $dispatcher;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function addEventListener($eventName, $listener, $priority = 0)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dispatcher->addListener($eventName, $listener, $priority);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dispatcher->addSubscriber($subscriber);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addViewTransformer(DataTransformerInterface $viewTransformer, $forcePrepend = false)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        if ($forcePrepend) {
            array_unshift($this->viewTransformers, $viewTransformer);
        } else {
            $this->viewTransformers[] = $viewTransformer;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resetViewTransformers()
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->viewTransformers = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addModelTransformer(DataTransformerInterface $modelTransformer, $forceAppend = false)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        if ($forceAppend) {
            $this->modelTransformers[] = $modelTransformer;
        } else {
            array_unshift($this->modelTransformers, $modelTransformer);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resetModelTransformers()
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->modelTransformers = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventDispatcher()
    {
        if ($this->locked && !$this->dispatcher instanceof ImmutableEventDispatcher) {
            $this->dispatcher = new ImmutableEventDispatcher($this->dispatcher);
        }

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
    public function getInheritData()
    {
        return $this->inheritData;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompound()
    {
        return $this->compound;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getViewTransformers()
    {
        return $this->viewTransformers;
    }

    /**
     * {@inheritdoc}
     */
    public function getModelTransformers()
    {
        return $this->modelTransformers;
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
        return array_key_exists($name, $this->attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
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
    public function getDataLocked()
    {
        return $this->dataLocked;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormFactory()
    {
        return $this->formFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestHandler()
    {
        if (null === $this->requestHandler) {
            if (null === self::$nativeRequestHandler) {
                self::$nativeRequestHandler = new NativeRequestHandler();
            }
            $this->requestHandler = self::$nativeRequestHandler;
        }

        return $this->requestHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function getAutoInitialize()
    {
        return $this->autoInitialize;
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
        return array_key_exists($name, $this->options);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name, $default = null)
    {
        return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($name, $value)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttributes(array $attributes)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->attributes = $attributes;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDataMapper(DataMapperInterface $dataMapper = null)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dataMapper = $dataMapper;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDisabled($disabled)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->disabled = (bool) $disabled;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setEmptyData($emptyData)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->emptyData = $emptyData;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setErrorBubbling($errorBubbling)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->errorBubbling = null === $errorBubbling ? null : (bool) $errorBubbling;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequired($required)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->required = (bool) $required;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPropertyPath($propertyPath)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        if (null !== $propertyPath && !$propertyPath instanceof PropertyPathInterface) {
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
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->mapped = $mapped;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setByReference($byReference)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->byReference = $byReference;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setInheritData($inheritData)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->inheritData = $inheritData;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCompound($compound)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->compound = $compound;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setType(ResolvedFormTypeInterface $type)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->type = $type;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setData($data)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->data = $data;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDataLocked($locked)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dataLocked = $locked;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormFactory(FormFactoryInterface $formFactory)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->formFactory = $formFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAction($action)
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }

        $this->action = $action;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMethod($method)
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }

        $this->method = strtoupper($method);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestHandler(RequestHandlerInterface $requestHandler)
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }

        $this->requestHandler = $requestHandler;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAutoInitialize($initialize)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->autoInitialize = (bool) $initialize;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormConfig()
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        // This method should be idempotent, so clone the builder
        $config = clone $this;
        $config->locked = true;

        return $config;
    }

    /**
     * Validates whether the given variable is a valid form name.
     *
     * @param string|int $name The tested form name
     *
     * @throws UnexpectedTypeException  if the name is not a string or an integer
     * @throws InvalidArgumentException if the name contains invalid characters
     */
    public static function validateName($name)
    {
        if (null !== $name && !\is_string($name) && !\is_int($name)) {
            throw new UnexpectedTypeException($name, 'string, integer or null');
        }

        if (!self::isValidName($name)) {
            throw new InvalidArgumentException(sprintf('The name "%s" contains illegal characters. Names should start with a letter, digit or underscore and only contain letters, digits, numbers, underscores ("_"), hyphens ("-") and colons (":").', $name));
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
     * @param string $name The tested form name
     *
     * @return bool Whether the name is valid
     */
    public static function isValidName($name)
    {
        return '' === $name || null === $name || preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_\-:]*$/D', $name);
    }
}
