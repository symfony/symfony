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

use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * A form button.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements \IteratorAggregate<string, FormInterface>
 */
class Button implements \IteratorAggregate, FormInterface
{
    private ?FormInterface $parent = null;
    private FormConfigInterface $config;
    private bool $submitted = false;

    /**
     * Creates a new button from a form configuration.
     */
    public function __construct(FormConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * Unsupported method.
     */
    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function offsetGet(mixed $offset): FormInterface
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * {@inheritdoc}
     */
    public function setParent(FormInterface $parent = null): static
    {
        if ($this->submitted) {
            throw new AlreadySubmittedException('You cannot set the parent of a submitted button.');
        }

        $this->parent = $parent;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?FormInterface
    {
        return $this->parent;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function add(string|FormInterface $child, string $type = null, array $options = []): static
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function get(string $name): FormInterface
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     */
    public function has(string $name): bool
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @throws BadMethodCallException
     */
    public function remove(string $name): static
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors(bool $deep = false, bool $flatten = true): FormErrorIterator
    {
        return new FormErrorIterator($this, []);
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @return $this
     */
    public function setData(mixed $modelData): static
    {
        // no-op, called during initialization of the form tree
        return $this;
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
    public function getNormData(): mixed
    {
        return null;
    }

    /**
     * Unsupported method.
     */
    public function getViewData(): mixed
    {
        return null;
    }

    /**
     * Unsupported method.
     */
    public function getExtraData(): array
    {
        return [];
    }

    /**
     * Returns the button's configuration.
     */
    public function getConfig(): FormConfigInterface
    {
        return $this->config;
    }

    /**
     * Returns whether the button is submitted.
     */
    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    /**
     * Returns the name by which the button is identified in forms.
     */
    public function getName(): string
    {
        return $this->config->getName();
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
     * @throws BadMethodCallException
     */
    public function addError(FormError $error): static
    {
        throw new BadMethodCallException('Buttons cannot have errors.');
    }

    /**
     * Unsupported method.
     */
    public function isValid(): bool
    {
        return true;
    }

    /**
     * Unsupported method.
     */
    public function isRequired(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDisabled(): bool
    {
        if ($this->parent?->isDisabled()) {
            return true;
        }

        return $this->config->getDisabled();
    }

    /**
     * Unsupported method.
     */
    public function isEmpty(): bool
    {
        return true;
    }

    /**
     * Unsupported method.
     */
    public function isSynchronized(): bool
    {
        return true;
    }

    /**
     * Unsupported method.
     */
    public function getTransformationFailure(): ?TransformationFailedException
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function initialize(): static
    {
        throw new BadMethodCallException('Buttons cannot be initialized. Call initialize() on the root form instead.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function handleRequest(mixed $request = null): static
    {
        throw new BadMethodCallException('Buttons cannot handle requests. Call handleRequest() on the root form instead.');
    }

    /**
     * Submits data to the button.
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the button has already been submitted
     */
    public function submit(array|string|null $submittedData, bool $clearMissing = true): static
    {
        if ($this->submitted) {
            throw new AlreadySubmittedException('A form can only be submitted once.');
        }

        $this->submitted = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot(): FormInterface
    {
        return $this->parent ? $this->parent->getRoot() : $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isRoot(): bool
    {
        return null === $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function createView(FormView $parent = null): FormView
    {
        if (null === $parent && $this->parent) {
            $parent = $this->parent->createView();
        }

        $type = $this->config->getType();
        $options = $this->config->getOptions();

        $view = $type->createView($this, $parent);

        $type->buildView($view, $this, $options);
        $type->finishView($view, $this, $options);

        return $view;
    }

    /**
     * Unsupported method.
     */
    public function count(): int
    {
        return 0;
    }

    /**
     * Unsupported method.
     */
    public function getIterator(): \EmptyIterator
    {
        return new \EmptyIterator();
    }
}
