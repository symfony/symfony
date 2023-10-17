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

    protected $locked = false;
    private $dispatcher;
    private $name;

    /**
     * @var PropertyPathInterface|string|null
     */
    private $propertyPath;

    private $mapped = true;
    private $byReference = true;
    private $inheritData = false;
    private $compound = false;

    /**
     * @var ResolvedFormTypeInterface
     */
    private $type;

    private $viewTransformers = [];
    private $modelTransformers = [];

    /**
     * @var DataMapperInterface|null
     */
    private $dataMapper;

    private $required = true;
    private $disabled = false;
    private $errorBubbling = false;

    /**
     * @var mixed
     */
    private $emptyData;

    private $attributes = [];

    /**
     * @var mixed
     */
    private $data;

    /**
     * @var string|null
     */
    private $dataClass;

    private $dataLocked = false;

    /**
     * @var FormFactoryInterface|null
     */
    private $formFactory;

    private $action = '';
    private $method = 'POST';

    /**
     * @var RequestHandlerInterface|null
     */
    private $requestHandler;

    private $autoInitialize = false;
    private $options;
    private $isEmptyCallback;

    /**
     * Creates an empty form configuration.
     *
     * @param string|null $name      The form name
     * @param string|null $dataClass The class of the form's data
     *
     * @throws InvalidArgumentException if the data class is not a valid class or if
     *                                  the name contains invalid characters
     */
    public function __construct(?string $name, ?string $dataClass, EventDispatcherInterface $dispatcher, array $options = [])
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

    public function addEventListener(string $eventName, callable $listener, int $priority = 0)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dispatcher->addListener($eventName, $listener, $priority);

        return $this;
    }

    public function addEventSubscriber(EventSubscriberInterface $subscriber)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dispatcher->addSubscriber($subscriber);

        return $this;
    }

    public function addViewTransformer(DataTransformerInterface $viewTransformer, bool $forcePrepend = false)
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

    public function resetViewTransformers()
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->viewTransformers = [];

        return $this;
    }

    public function addModelTransformer(DataTransformerInterface $modelTransformer, bool $forceAppend = false)
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

    public function resetModelTransformers()
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->modelTransformers = [];

        return $this;
    }

    public function getEventDispatcher()
    {
        if ($this->locked && !$this->dispatcher instanceof ImmutableEventDispatcher) {
            $this->dispatcher = new ImmutableEventDispatcher($this->dispatcher);
        }

        return $this->dispatcher;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPropertyPath()
    {
        return $this->propertyPath;
    }

    public function getMapped()
    {
        return $this->mapped;
    }

    public function getByReference()
    {
        return $this->byReference;
    }

    public function getInheritData()
    {
        return $this->inheritData;
    }

    public function getCompound()
    {
        return $this->compound;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getViewTransformers()
    {
        return $this->viewTransformers;
    }

    public function getModelTransformers()
    {
        return $this->modelTransformers;
    }

    public function getDataMapper()
    {
        return $this->dataMapper;
    }

    public function getRequired()
    {
        return $this->required;
    }

    public function getDisabled()
    {
        return $this->disabled;
    }

    public function getErrorBubbling()
    {
        return $this->errorBubbling;
    }

    public function getEmptyData()
    {
        return $this->emptyData;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function hasAttribute(string $name)
    {
        return \array_key_exists($name, $this->attributes);
    }

    public function getAttribute(string $name, $default = null)
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getDataClass()
    {
        return $this->dataClass;
    }

    public function getDataLocked()
    {
        return $this->dataLocked;
    }

    public function getFormFactory()
    {
        if (!isset($this->formFactory)) {
            throw new BadMethodCallException('The form factory must be set before retrieving it.');
        }

        return $this->formFactory;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getMethod()
    {
        return $this->method;
    }

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

    public function getAutoInitialize()
    {
        return $this->autoInitialize;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function hasOption(string $name)
    {
        return \array_key_exists($name, $this->options);
    }

    public function getOption(string $name, $default = null)
    {
        return \array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }

    public function getIsEmptyCallback(): ?callable
    {
        return $this->isEmptyCallback;
    }

    public function setAttribute(string $name, $value)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->attributes[$name] = $value;

        return $this;
    }

    public function setAttributes(array $attributes)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->attributes = $attributes;

        return $this;
    }

    public function setDataMapper(DataMapperInterface $dataMapper = null)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dataMapper = $dataMapper;

        return $this;
    }

    public function setDisabled(bool $disabled)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->disabled = $disabled;

        return $this;
    }

    public function setEmptyData($emptyData)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->emptyData = $emptyData;

        return $this;
    }

    public function setErrorBubbling(bool $errorBubbling)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->errorBubbling = $errorBubbling;

        return $this;
    }

    public function setRequired(bool $required)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->required = $required;

        return $this;
    }

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

    public function setMapped(bool $mapped)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->mapped = $mapped;

        return $this;
    }

    public function setByReference(bool $byReference)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->byReference = $byReference;

        return $this;
    }

    public function setInheritData(bool $inheritData)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->inheritData = $inheritData;

        return $this;
    }

    public function setCompound(bool $compound)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->compound = $compound;

        return $this;
    }

    public function setType(ResolvedFormTypeInterface $type)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->type = $type;

        return $this;
    }

    public function setData($data)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->data = $data;

        return $this;
    }

    public function setDataLocked(bool $locked)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dataLocked = $locked;

        return $this;
    }

    public function setFormFactory(FormFactoryInterface $formFactory)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->formFactory = $formFactory;

        return $this;
    }

    public function setAction(string $action)
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }

        $this->action = $action;

        return $this;
    }

    public function setMethod(string $method)
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }

        $this->method = strtoupper($method);

        return $this;
    }

    public function setRequestHandler(RequestHandlerInterface $requestHandler)
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }

        $this->requestHandler = $requestHandler;

        return $this;
    }

    public function setAutoInitialize(bool $initialize)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->autoInitialize = $initialize;

        return $this;
    }

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

    public function setIsEmptyCallback(?callable $isEmptyCallback)
    {
        $this->isEmptyCallback = $isEmptyCallback;

        return $this;
    }

    /**
     * Validates whether the given variable is a valid form name.
     *
     * @throws InvalidArgumentException if the name contains invalid characters
     *
     * @internal
     */
    final public static function validateName(?string $name)
    {
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
     */
    final public static function isValidName(?string $name): bool
    {
        return '' === $name || null === $name || preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_\-:]*$/D', $name);
    }
}
