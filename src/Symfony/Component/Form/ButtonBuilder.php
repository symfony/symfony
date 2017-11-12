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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\BadMethodCallException;

/**
 * A builder for {@link Button} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonBuilder implements \IteratorAggregate, FormBuilderInterface
{
    /**
     * @var bool
     */
    protected $locked = false;

    /**
     * @var bool
     */
    private $disabled;

    /**
     * @var ResolvedFormTypeInterface
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $attributes = array();

    /**
     * @var array
     */
    private $options;

    /**
     * @throws InvalidArgumentException if the name is empty
     */
    public function __construct(?string $name, array $options = array())
    {
        if ('' === $name || null === $name) {
            throw new InvalidArgumentException('Buttons cannot have empty names.');
        }

        $this->name = $name;
        $this->options = $options;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param string|int|FormBuilderInterface $child
     * @param string|FormTypeInterface        $type
     *
     * @throws BadMethodCallException
     */
    public function add($child, $type = null, array $options = array()): void
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param string|FormTypeInterface $type
     *
     * @throws BadMethodCallException
     */
    public function create(string $name, $type = null, array $options = array()): void
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     *
     * @throws BadMethodCallException
     */
    public function get(string $name): void
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     *
     * @throws BadMethodCallException
     */
    public function remove(string $name): void
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
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
        return array();
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
     * This method should not be invoked.
     *
     * @param callable $listener
     *
     * @throws BadMethodCallException
     */
    public function addEventListener(string $eventName, callable $listener, int $priority = 0): void
    {
        throw new BadMethodCallException('Buttons do not support event listeners.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber): void
    {
        throw new BadMethodCallException('Buttons do not support event subscribers.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     *
     * @throws BadMethodCallException
     */
    public function addViewTransformer(DataTransformerInterface $viewTransformer, bool $forcePrepend = false): void
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function resetViewTransformers(): void
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     *
     * @throws BadMethodCallException
     */
    public function addModelTransformer(DataTransformerInterface $modelTransformer, bool $forceAppend = false): void
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function resetModelTransformers(): void
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
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
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function setDataMapper(DataMapperInterface $dataMapper = null): void
    {
        throw new BadMethodCallException('Buttons do not support data mappers.');
    }

    /**
     * Set whether the button is disabled.
     *
     * @param bool $disabled Whether the button is disabled
     *
     * @return $this
     */
    public function setDisabled(bool $disabled)
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param mixed $emptyData
     *
     * @throws BadMethodCallException
     */
    public function setEmptyData($emptyData): void
    {
        throw new BadMethodCallException('Buttons do not support empty data.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     *
     * @throws BadMethodCallException
     */
    public function setErrorBubbling(bool $errorBubbling): void
    {
        throw new BadMethodCallException('Buttons do not support error bubbling.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     *
     * @throws BadMethodCallException
     */
    public function setRequired(bool $required): void
    {
        throw new BadMethodCallException('Buttons cannot be required.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param null $propertyPath
     *
     * @throws BadMethodCallException
     */
    public function setPropertyPath($propertyPath): void
    {
        throw new BadMethodCallException('Buttons do not support property paths.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     *
     * @throws BadMethodCallException
     */
    public function setMapped(bool $mapped): void
    {
        throw new BadMethodCallException('Buttons do not support data mapping.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     *
     * @throws BadMethodCallException
     */
    public function setByReference(bool $byReference): void
    {
        throw new BadMethodCallException('Buttons do not support data mapping.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     *
     * @throws BadMethodCallException
     */
    public function setCompound(bool $compound): void
    {
        throw new BadMethodCallException('Buttons cannot be compound.');
    }

    /**
     * Sets the type of the button.
     *
     * @return $this
     */
    public function setType(ResolvedFormTypeInterface $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param mixed $data
     *
     * @throws BadMethodCallException
     */
    public function setData($data): void
    {
        throw new BadMethodCallException('Buttons do not support data.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     *
     * @throws BadMethodCallException
     */
    public function setDataLocked(bool $locked): void
    {
        throw new BadMethodCallException('Buttons do not support data locking.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function setFormFactory(FormFactoryInterface $formFactory): void
    {
        throw new BadMethodCallException('Buttons do not support form factories.');
    }

    /**
     * Unsupported method.
     *
     *
     * @throws BadMethodCallException
     */
    public function setAction(string $action): void
    {
        throw new BadMethodCallException('Buttons do not support actions.');
    }

    /**
     * Unsupported method.
     *
     *
     * @throws BadMethodCallException
     */
    public function setMethod(string $method): void
    {
        throw new BadMethodCallException('Buttons do not support methods.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setRequestHandler(RequestHandlerInterface $requestHandler): void
    {
        throw new BadMethodCallException('Buttons do not support request handlers.');
    }

    /**
     * Unsupported method.
     *
     *
     * @return $this
     *
     * @throws BadMethodCallException
     */
    public function setAutoInitialize(bool $initialize)
    {
        if (true === $initialize) {
            throw new BadMethodCallException('Buttons do not support automatic initialization.');
        }

        return $this;
    }

    /**
     * Unsupported method.
     *
     *
     * @throws BadMethodCallException
     */
    public function setInheritData(bool $inheritData): void
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
     */
    public function getEventDispatcher(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Unsupported method.
     */
    public function getPropertyPath(): void
    {
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
        return array();
    }

    /**
     * Unsupported method.
     *
     * @return array Always returns an empty array
     */
    public function getModelTransformers(): array
    {
        return array();
    }

    /**
     * Unsupported method.
     */
    public function getDataMapper(): void
    {
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
    public function getEmptyData(): void
    {
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
     * @param string $name The attribute name
     *
     * @return bool Whether the attribute exists
     */
    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Returns the value of the given attribute.
     *
     * @param string $name    The attribute name
     * @param mixed  $default The value returned if the attribute does not exist
     *
     * @return mixed The attribute value
     */
    public function getAttribute(string $name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * Unsupported method.
     */
    public function getData(): void
    {
    }

    /**
     * Unsupported method.
     */
    public function getDataClass(): void
    {
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
    public function getFormFactory(): void
    {
    }

    /**
     * Unsupported method.
     */
    public function getAction(): void
    {
    }

    /**
     * Unsupported method.
     */
    public function getMethod(): void
    {
    }

    /**
     * Unsupported method.
     */
    public function getRequestHandler(): void
    {
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
     * @param string $name The option name,
     *
     * @return bool Whether the option exists
     */
    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    /**
     * Returns the value of a specific option.
     *
     * @param string $name    The option name
     * @param mixed  $default The value returned if the option does not exist
     *
     * @return mixed The option value
     */
    public function getOption(string $name, $default = null)
    {
        return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
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
