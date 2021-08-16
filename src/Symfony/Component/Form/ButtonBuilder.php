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
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * A builder for {@link Button} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonBuilder implements \IteratorAggregate, FormBuilderInterface
{
    protected $locked = false;

    private bool $disabled = false;
    private ResolvedFormTypeInterface $type;
    private string $name;
    private array $attributes = [];
    private array $options;

    /**
     * @throws InvalidArgumentException if the name is empty
     */
    public function __construct(?string $name, array $options = [])
    {
        if ('' === $name || null === $name) {
            throw new InvalidArgumentException('Buttons cannot have empty names.');
        }

        $this->name = $name;
        $this->options = $options;

        FormConfigBuilder::validateName($name);
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function add(string|FormBuilderInterface $child, string $type = null, array $options = []): static
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function create(string $name, string $type = null, array $options = []): FormBuilderInterface
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function get(string $name): FormBuilderInterface
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function remove(string $name): static
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function has(string $name): bool
    {
        return false;
    }

    /**
     * Returns the children.
     *
     * @return array Always returns an empty array
     */
    public function all(): array
    {
        return [];
    }

    /**
     * Creates the button.
     *
     * @return Button The button
     */
    public function getForm(): Button
    {
        return new Button($this->getFormConfig());
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function addEventListener(string $eventName, callable $listener, int $priority = 0): static
    {
        throw new BadMethodCallException('Buttons do not support event listeners.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber): static
    {
        throw new BadMethodCallException('Buttons do not support event subscribers.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function addViewTransformer(DataTransformerInterface $viewTransformer, bool $forcePrepend = false): static
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function resetViewTransformers(): static
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function addModelTransformer(DataTransformerInterface $modelTransformer, bool $forceAppend = false): static
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function resetModelTransformers(): static
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttributes(array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setDataMapper(DataMapperInterface $dataMapper = null): static
    {
        throw new BadMethodCallException('Buttons do not support data mappers.');
    }

    /**
     * Set whether the button is disabled.
     *
     * @return $this
     */
    public function setDisabled(bool $disabled): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setEmptyData(mixed $emptyData): static
    {
        throw new BadMethodCallException('Buttons do not support empty data.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setErrorBubbling(bool $errorBubbling): static
    {
        throw new BadMethodCallException('Buttons do not support error bubbling.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setRequired(bool $required): static
    {
        throw new BadMethodCallException('Buttons cannot be required.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setPropertyPath(string|PropertyPathInterface|null $propertyPath): static
    {
        throw new BadMethodCallException('Buttons do not support property paths.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setMapped(bool $mapped): static
    {
        throw new BadMethodCallException('Buttons do not support data mapping.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setByReference(bool $byReference): static
    {
        throw new BadMethodCallException('Buttons do not support data mapping.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setCompound(bool $compound): static
    {
        throw new BadMethodCallException('Buttons cannot be compound.');
    }

    /**
     * Sets the type of the button.
     *
     * @return $this
     */
    public function setType(ResolvedFormTypeInterface $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setData(mixed $data): static
    {
        throw new BadMethodCallException('Buttons do not support data.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setDataLocked(bool $locked): static
    {
        throw new BadMethodCallException('Buttons do not support data locking.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setFormFactory(FormFactoryInterface $formFactory)
    {
        throw new BadMethodCallException('Buttons do not support form factories.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setAction(string $action): static
    {
        throw new BadMethodCallException('Buttons do not support actions.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setMethod(string $method): static
    {
        throw new BadMethodCallException('Buttons do not support methods.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setRequestHandler(RequestHandlerInterface $requestHandler): static
    {
        throw new BadMethodCallException('Buttons do not support request handlers.');
    }

    /**
     * Unsupported method.
     *
     * @return $this
     *
     * @throws BadMethodCallException
     */
    public function setAutoInitialize(bool $initialize): static
    {
        if (true === $initialize) {
            throw new BadMethodCallException('Buttons do not support automatic initialization.');
        }

        return $this;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setInheritData(bool $inheritData): static
    {
        throw new BadMethodCallException('Buttons do not support data inheritance.');
    }

    /**
     * Builds and returns the button configuration.
     */
    public function getFormConfig(): FormConfigInterface
    {
        // This method should be idempotent, so clone the builder
        $config = clone $this;
        $config->locked = true;

        return $config;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setIsEmptyCallback(?callable $isEmptyCallback): static
    {
        throw new BadMethodCallException('Buttons do not support "is empty" callback.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        throw new BadMethodCallException('Buttons do not support event dispatching.');
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Unsupported method.
     */
    public function getPropertyPath(): ?PropertyPathInterface
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getMapped(): bool
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getByReference(): bool
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getCompound(): bool
    {
        return false;
    }

    /**
     * Returns the form type used to construct the button.
     *
     * @return ResolvedFormTypeInterface The button's type
     */
    public function getType(): ResolvedFormTypeInterface
    {
        return $this->type;
    }

    /**
     * Unsupported method.
     *
     * @return array Always returns an empty array
     */
    public function getViewTransformers(): array
    {
        return [];
    }

    /**
     * Unsupported method.
     *
     * @return array Always returns an empty array
     */
    public function getModelTransformers(): array
    {
        return [];
    }

    /**
     * Unsupported method.
     */
    public function getDataMapper(): ?DataMapperInterface
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getRequired(): bool
    {
        return false;
    }

    /**
     * Returns whether the button is disabled.
     *
     * @return bool Whether the button is disabled
     */
    public function getDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getErrorBubbling(): bool
    {
        return false;
    }

    /**
     * Unsupported method.
     */
    public function getEmptyData(): mixed
    {
        return null;
    }

    /**
     * Returns additional attributes of the button.
     *
     * @return array An array of key-value combinations
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Returns whether the attribute with the given name exists.
     *
     * @return bool Whether the attribute exists
     */
    public function hasAttribute(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }

    /**
     * Returns the value of the given attribute.
     *
     * @return mixed The attribute value
     */
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * Unsupported method.
     */
    public function getData(): mixed
    {
        return null;
    }

    /**
     * Unsupported method.
     */
    public function getDataClass(): ?string
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getDataLocked(): bool
    {
        return false;
    }

    /**
     * Unsupported method.
     */
    public function getFormFactory(): FormFactoryInterface
    {
        throw new BadMethodCallException('Buttons do not support adding children.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function getAction(): string
    {
        throw new BadMethodCallException('Buttons do not support actions.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function getMethod(): string
    {
        throw new BadMethodCallException('Buttons do not support methods.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function getRequestHandler(): RequestHandlerInterface
    {
        throw new BadMethodCallException('Buttons do not support request handlers.');
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getAutoInitialize(): bool
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getInheritData(): bool
    {
        return false;
    }

    /**
     * Returns all options passed during the construction of the button.
     *
     * @return array The passed options
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Returns whether a specific option exists.
     *
     * @return bool Whether the option exists
     */
    public function hasOption(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }

    /**
     * Returns the value of a specific option.
     *
     * @return mixed The option value
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function getIsEmptyCallback(): ?callable
    {
        throw new BadMethodCallException('Buttons do not support "is empty" callback.');
    }

    /**
     * Unsupported method.
     *
     * @return int Always returns 0
     */
    public function count(): int
    {
        return 0;
    }

    /**
     * Unsupported method.
     *
     * @return \EmptyIterator Always returns an empty iterator
     */
    public function getIterator(): \EmptyIterator
    {
        return new \EmptyIterator();
    }
}
