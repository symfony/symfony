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
     */
    private static NativeRequestHandler $nativeRequestHandler;

    /** @var bool */
    protected $locked = false;

    private $dispatcher;
    private string $name;
    private $propertyPath = null;
    private bool $mapped = true;
    private bool $byReference = true;
    private bool $inheritData = false;
    private bool $compound = false;
    private $type;
    private array $viewTransformers = [];
    private array $modelTransformers = [];
    private $dataMapper = null;
    private bool $required = true;
    private bool $disabled = false;
    private bool $errorBubbling = false;
    private mixed $emptyData = null;
    private array $attributes = [];
    private mixed $data = null;
    private ?string $dataClass;
    private bool $dataLocked = false;
    private $formFactory;
    private string $action = '';
    private string $method = 'POST';
    private $requestHandler;
    private bool $autoInitialize = false;
    private array $options;
    private ?\Closure $isEmptyCallback = null;

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

    /**
     * {@inheritdoc}
     */
    public function addEventListener(string $eventName, callable $listener, int $priority = 0): static
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
    public function addEventSubscriber(EventSubscriberInterface $subscriber): static
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
    public function addViewTransformer(DataTransformerInterface $viewTransformer, bool $forcePrepend = false): static
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
    public function resetViewTransformers(): static
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
    public function addModelTransformer(DataTransformerInterface $modelTransformer, bool $forceAppend = false): static
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
    public function resetModelTransformers(): static
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
    public function getEventDispatcher(): EventDispatcherInterface
    {
        if ($this->locked && !$this->dispatcher instanceof ImmutableEventDispatcher) {
            $this->dispatcher = new ImmutableEventDispatcher($this->dispatcher);
        }

        return $this->dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyPath(): ?PropertyPathInterface
    {
        return $this->propertyPath;
    }

    /**
     * {@inheritdoc}
     */
    public function getMapped(): bool
    {
        return $this->mapped;
    }

    /**
     * {@inheritdoc}
     */
    public function getByReference(): bool
    {
        return $this->byReference;
    }

    /**
     * {@inheritdoc}
     */
    public function getInheritData(): bool
    {
        return $this->inheritData;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompound(): bool
    {
        return $this->compound;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): ResolvedFormTypeInterface
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getViewTransformers(): array
    {
        return $this->viewTransformers;
    }

    /**
     * {@inheritdoc}
     */
    public function getModelTransformers(): array
    {
        return $this->modelTransformers;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataMapper(): ?DataMapperInterface
    {
        return $this->dataMapper;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequired(): bool
    {
        return $this->required;
    }

    /**
     * {@inheritdoc}
     */
    public function getDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorBubbling(): bool
    {
        return $this->errorBubbling;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmptyData(): mixed
    {
        return $this->emptyData;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAttribute(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataClass(): ?string
    {
        return $this->dataClass;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataLocked(): bool
    {
        return $this->dataLocked;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormFactory(): FormFactoryInterface
    {
        if (!isset($this->formFactory)) {
            throw new BadMethodCallException('The form factory must be set before retrieving it.');
        }

        return $this->formFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestHandler(): RequestHandlerInterface
    {
        return $this->requestHandler ??= self::$nativeRequestHandler ??= new NativeRequestHandler();
    }

    /**
     * {@inheritdoc}
     */
    public function getAutoInitialize(): bool
    {
        return $this->autoInitialize;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getIsEmptyCallback(): ?callable
    {
        return $this->isEmptyCallback;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute(string $name, mixed $value): static
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
    public function setAttributes(array $attributes): static
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
    public function setDataMapper(DataMapperInterface $dataMapper = null): static
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
    public function setDisabled(bool $disabled): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->disabled = $disabled;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setEmptyData(mixed $emptyData): static
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
    public function setErrorBubbling(bool $errorBubbling): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->errorBubbling = $errorBubbling;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequired(bool $required): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->required = $required;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPropertyPath(string|PropertyPathInterface|null $propertyPath): static
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
    public function setMapped(bool $mapped): static
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
    public function setByReference(bool $byReference): static
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
    public function setInheritData(bool $inheritData): static
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
    public function setCompound(bool $compound): static
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
    public function setType(ResolvedFormTypeInterface $type): static
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
    public function setData(mixed $data): static
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
    public function setDataLocked(bool $locked): static
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
    public function setAction(string $action): static
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
    public function setMethod(string $method): static
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
    public function setRequestHandler(RequestHandlerInterface $requestHandler): static
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
    public function setAutoInitialize(bool $initialize): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->autoInitialize = $initialize;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormConfig(): FormConfigInterface
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
     * {@inheritdoc}
     */
    public function setIsEmptyCallback(?callable $isEmptyCallback): static
    {
        $this->isEmptyCallback = null === $isEmptyCallback || $isEmptyCallback instanceof \Closure ? $isEmptyCallback : \Closure::fromCallable($isEmptyCallback);

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
