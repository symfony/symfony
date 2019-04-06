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
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * A builder for creating {@link Form} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormBuilder extends FormConfigBuilder implements \IteratorAggregate, FormBuilderInterface
{
    /**
     * The children of the form builder.
     *
     * @var FormBuilderInterface[]
     */
    private $children = [];

    /**
     * The data of children who haven't been converted to form builders yet.
     *
     * @var array
     */
    private $unresolvedChildren = [];

    public function __construct(?string $name, ?string $dataClass, EventDispatcherInterface $dispatcher, FormFactoryInterface $factory, array $options = [])
    {
        parent::__construct($name, $dataClass, $dispatcher, $options);

        $this->setFormFactory($factory);
    }

    /**
     * {@inheritdoc}
     */
    public function add($child, $type = null, array $options = [])
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

        if (!\is_string($child) && !\is_int($child)) {
            throw new UnexpectedTypeException($child, 'string, integer or Symfony\Component\Form\FormBuilderInterface');
        }

        if (null !== $type && !\is_string($type) && !$type instanceof FormTypeInterface) {
            throw new UnexpectedTypeException($type, 'string or Symfony\Component\Form\FormTypeInterface');
        }

        // Add to "children" to maintain order
        $this->children[$child] = null;
        $this->unresolvedChildren[$child] = [$type, $options];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function create($name, $type = null, array $options = [])
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        if (null === $type && null === $this->getDataClass()) {
            $type = 'Symfony\Component\Form\Extension\Core\Type\TextType';
        }

        if (null !== $type) {
            return $this->getFormFactory()->createNamedBuilder($name, $type, null, $options);
        }

        return $this->getFormFactory()->createBuilderForProperty($this->getDataClass(), $name, null, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
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

    /**
     * {@inheritdoc}
     */
    public function remove($name)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        unset($this->unresolvedChildren[$name], $this->children[$name]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return isset($this->unresolvedChildren[$name]) || isset($this->children[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->resolveChildren();

        return $this->children;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return \count($this->children);
    }

    /**
     * {@inheritdoc}
     */
    public function getFormConfig()
    {
        /** @var $config self */
        $config = parent::getFormConfig();

        $config->children = [];
        $config->unresolvedChildren = [];

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public function getForm()
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
     * {@inheritdoc}
     *
     * @return FormBuilderInterface[]|\Traversable
     */
    public function getIterator()
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
        list($type, $options) = $this->unresolvedChildren[$name];

        unset($this->unresolvedChildren[$name]);

        return $this->children[$name] = $this->create($name, $type, $options);
    }

    /**
     * Converts all unresolved children into {@link FormBuilder} instances.
     */
    private function resolveChildren()
    {
        foreach ($this->unresolvedChildren as $name => $info) {
            $this->children[$name] = $this->create($name, $info[0], $info[1]);
        }

        $this->unresolvedChildren = [];
    }
}
