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

    private EventDispatcherInterface $dispatcher;
    private string $name;
    private ?PropertyPathInterface $propertyPath = null;
    private bool $mapped = true;
    private bool $byReference = true;
    private bool $inheritData = false;
    private bool $compound = false;
    private ResolvedFormTypeInterface $type;
    private array $viewTransformers = [];
    private array $modelTransformers = [];
    private ?DataMapperInterface $dataMapper = null;
    private bool $required = true;
    private bool $disabled = false;
    private bool $errorBubbling = false;
    private mixed $emptyData = null;
    private array $attributes = [];
    private mixed $data = null;
    private ?string $dataClass;
    private bool $dataLocked = false;
    private FormFactoryInterface $formFactory;
    private string $action = '';
    private string $method = 'POST';
    private RequestHandlerInterface $requestHandler;
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

    public function addEventListener(string $eventName, callable $listener, int $priority = 0): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dispatcher->addListener($eventName, $listener, $priority);

        return $this;
    }

    public function addEventSubscriber(EventSubscriberInterface $subscriber): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dispatcher->addSubscriber($subscriber);

        return $this;
    }

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

    public function resetViewTransformers(): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->viewTransformers = [];

        return $this;
    }

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

    public function resetModelTransformers(): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->modelTransformers = [];

        return $this;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        if ($this->locked && !$this->dispatcher instanceof ImmutableEventDispatcher) {
            $this->dispatcher = new ImmutableEventDispatcher($this->dispatcher);
        }

        return $this->dispatcher;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPropertyPath(): ?PropertyPathInterface
    {
        return $this->propertyPath;
    }

    public function getMapped(): bool
    {
        return $this->mapped;
    }

    public function getByReference(): bool
    {
        return $this->byReference;
    }

    public function getInheritData(): bool
    {
        return $this->inheritData;
    }

    public function getCompound(): bool
    {
        return $this->compound;
    }

    public function getType(): ResolvedFormTypeInterface
    {
        return $this->type;
    }

    public function getViewTransformers(): array
    {
        return $this->viewTransformers;
    }

    public function getModelTransformers(): array
    {
        return $this->modelTransformers;
    }

    public function getDataMapper(): ?DataMapperInterface
    {
        return $this->dataMapper;
    }

    public function getRequired(): bool
    {
        return $this->required;
    }

    public function getDisabled(): bool
    {
        return $this->disabled;
    }

    public function getErrorBubbling(): bool
    {
        return $this->errorBubbling;
    }

    public function getEmptyData(): mixed
    {
        return $this->emptyData;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function hasAttribute(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getDataClass(): ?string
    {
        return $this->dataClass;
    }

    public function getDataLocked(): bool
    {
        return $this->dataLocked;
    }

    public function getFormFactory(): FormFactoryInterface
    {
        if (!isset($this->formFactory)) {
            throw new BadMethodCallException('The form factory must be set before retrieving it.');
        }

        return $this->formFactory;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getRequestHandler(): RequestHandlerInterface
    {
        return $this->requestHandler ??= self::$nativeRequestHandler ??= new NativeRequestHandler();
    }

    public function getAutoInitialize(): bool
    {
        return $this->autoInitialize;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function hasOption(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }

    public function getOption(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }

    public function getIsEmptyCallback(): ?callable
    {
        return $this->isEmptyCallback;
    }

    public function setAttribute(string $name, mixed $value): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->attributes[$name] = $value;

        return $this;
    }

    public function setAttributes(array $attributes): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->attributes = $attributes;

        return $this;
    }

    public function setDataMapper(DataMapperInterface $dataMapper = null): static
    {
        if (1 > \func_num_args()) {
            trigger_deprecation('symfony/form', '6.2', 'Calling "%s()" without any arguments is deprecated, pass null explicitly instead.', __METHOD__);
        }
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dataMapper = $dataMapper;

        return $this;
    }

    public function setDisabled(bool $disabled): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->disabled = $disabled;

        return $this;
    }

    public function setEmptyData(mixed $emptyData): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->emptyData = $emptyData;

        return $this;
    }

    public function setErrorBubbling(bool $errorBubbling): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->errorBubbling = $errorBubbling;

        return $this;
    }

    public function setRequired(bool $required): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->required = $required;

        return $this;
    }

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

    public function setMapped(bool $mapped): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->mapped = $mapped;

        return $this;
    }

    public function setByReference(bool $byReference): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->byReference = $byReference;

        return $this;
    }

    public function setInheritData(bool $inheritData): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->inheritData = $inheritData;

        return $this;
    }

    public function setCompound(bool $compound): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->compound = $compound;

        return $this;
    }

    public function setType(ResolvedFormTypeInterface $type): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->type = $type;

        return $this;
    }

    public function setData(mixed $data): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->data = $data;

        return $this;
    }

    public function setDataLocked(bool $locked): static
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

    public function setAction(string $action): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }

        $this->action = $action;

        return $this;
    }

    public function setMethod(string $method): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }

        $this->method = strtoupper($method);

        return $this;
    }

    public function setRequestHandler(RequestHandlerInterface $requestHandler): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('The config builder cannot be modified anymore.');
        }

        $this->requestHandler = $requestHandler;

        return $this;
    }

    public function setAutoInitialize(bool $initialize): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->autoInitialize = $initialize;

        return $this;
    }

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

    public function setIsEmptyCallback(?callable $isEmptyCallback): static
    {
        $this->isEmptyCallback = null === $isEmptyCallback ? null : $isEmptyCallback(...);

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
