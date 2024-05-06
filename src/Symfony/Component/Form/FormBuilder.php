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
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * A builder for creating {@link Form} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements \IteratorAggregate<string, FormBuilderInterface>
 */
class FormBuilder extends FormConfigBuilder implements \IteratorAggregate, FormBuilderInterface
{
    /**
     * The children of the form builder.
     *
     * @var FormBuilderInterface[]
     */
    private array $children = [];

    /**
     * The data of children who haven't been converted to form builders yet.
     */
    private array $unresolvedChildren = [];

    public function __construct(?string $name, ?string $dataClass, EventDispatcherInterface $dispatcher, FormFactoryInterface $factory, array $options = [])
    {
        parent::__construct($name, $dataClass, $dispatcher, $options);

        $this->setFormFactory($factory);
    }

    public function add(FormBuilderInterface|string $child, ?string $type = null, array $options = []): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        if ($child instanceof FormBuilderInterface) {
            $this->children[$child->getName()] = $child;

            // In case an unresolved child with the same name exists
            unset($this->unresolvedChildren[$child->getName()]);

            return $this;
        }

        // Add to "children" to maintain order
        $this->children[$child] = null;
        $this->unresolvedChildren[$child] = [$type, $options];

        return $this;
    }

    public function create(string $name, ?string $type = null, array $options = []): FormBuilderInterface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        if (null === $type && null === $this->getDataClass()) {
            $type = TextType::class;
        }

        if (null !== $type) {
            return $this->getFormFactory()->createNamedBuilder($name, $type, null, $options);
        }

        return $this->getFormFactory()->createBuilderForProperty($this->getDataClass(), $name, null, $options);
    }

    public function get(string $name): FormBuilderInterface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        if (isset($this->unresolvedChildren[$name])) {
            return $this->resolveChild($name);
        }

        if (isset($this->children[$name])) {
            return $this->children[$name];
        }

        throw new InvalidArgumentException(sprintf('The child with the name "%s" does not exist.', $name));
    }

    public function remove(string $name): static
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        unset($this->unresolvedChildren[$name], $this->children[$name]);

        return $this;
    }

    public function has(string $name): bool
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return isset($this->unresolvedChildren[$name]) || isset($this->children[$name]);
    }

    public function all(): array
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->resolveChildren();

        return $this->children;
    }

    public function count(): int
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return \count($this->children);
    }

    public function getFormConfig(): FormConfigInterface
    {
        /** @var $config self */
        $config = parent::getFormConfig();

        $config->children = [];
        $config->unresolvedChildren = [];

        return $config;
    }

    public function getForm(): FormInterface
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->resolveChildren();

        $form = new Form($this->getFormConfig());

        foreach ($this->children as $child) {
            // Automatic initialization is only supported on root forms
            $form->add($child->setAutoInitialize(false)->getForm());
        }

        if ($this->getAutoInitialize()) {
            // Automatically initialize the form if it is configured so
            $form->initialize();
        }

        return $form;
    }

    /**
     * @return \Traversable<string, FormBuilderInterface>
     */
    public function getIterator(): \Traversable
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return new \ArrayIterator($this->all());
    }

    /**
     * Converts an unresolved child into a {@link FormBuilderInterface} instance.
     */
    private function resolveChild(string $name): FormBuilderInterface
    {
        [$type, $options] = $this->unresolvedChildren[$name];

        unset($this->unresolvedChildren[$name]);

        return $this->children[$name] = $this->create($name, $type, $options);
    }

    /**
     * Converts all unresolved children into {@link FormBuilder} instances.
     */
    private function resolveChildren(): void
    {
        foreach ($this->unresolvedChildren as $name => $info) {
            $this->children[$name] = $this->create($name, $info[0], $info[1]);
        }

        $this->unresolvedChildren = [];
    }
}
