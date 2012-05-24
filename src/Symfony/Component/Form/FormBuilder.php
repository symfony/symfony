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

use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\CircularReferenceException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A builder for creating {@link Form} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormBuilder extends FormConfig implements \IteratorAggregate, FormBuilderInterface
{
    /**
     * The form factory.
     *
     * @var FormFactoryInterface
     */
    private $factory;

    /**
     * The children of the form builder.
     *
     * @var array
     */
    private $children = array();

    /**
     * The data of children who haven't been converted to form builders yet.
     *
     * @var array
     */
    private $unresolvedChildren = array();

    private $currentLoadingType;

    /**
     * The parent of this builder
     * @var FormBuilder
     */
    private $parent;

    /**
     * Creates a new form builder.
     *
     * @param string                   $name
     * @param string                   $dataClass
     * @param EventDispatcherInterface $dispatcher
     * @param FormFactoryInterface     $factory
     */
    public function __construct($name, $dataClass, EventDispatcherInterface $dispatcher, FormFactoryInterface $factory, array $options = array())
    {
        parent::__construct($name, $dataClass, $dispatcher, $options);

        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormFactory()
    {
        return $this->factory;
    }

    /**
     * {@inheritdoc}
     */
    public function add($child, $type = null, array $options = array())
    {
        if ($child instanceof self) {
            $child->setParent($this);
            $this->children[$child->getName()] = $child;

            // In case an unresolved child with the same name exists
            unset($this->unresolvedChildren[$child->getName()]);

            return $this;
        }

        if (!is_string($child)) {
            throw new UnexpectedTypeException($child, 'string or Symfony\Component\Form\FormBuilder');
        }

        if (null !== $type && !is_string($type) && !$type instanceof FormTypeInterface) {
            throw new UnexpectedTypeException($type, 'string or Symfony\Component\Form\FormTypeInterface');
        }

        if ($this->currentLoadingType && ($type instanceof FormTypeInterface ? $type->getName() : $type) == $this->currentLoadingType) {
            throw new CircularReferenceException(is_string($type) ? $this->getFormFactory()->getType($type) : $type);
        }

        $this->unresolvedChildren[$child] = array(
            'type'    => $type,
            'options' => $options,
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function create($name, $type = null, array $options = array())
    {
        if (null === $type && null === $this->getDataClass()) {
            $type = 'text';
        }

        if (null !== $type) {
            return $this->getFormFactory()->createNamedBuilder($name, $type, null, $options, $this);
        }

        return $this->getFormFactory()->createBuilderForProperty($this->getDataClass(), $name, null, $options, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (isset($this->unresolvedChildren[$name])) {
            return $this->resolveChild($name);
        }

        if (isset($this->children[$name])) {
            return $this->children[$name];
        }

        throw new FormException(sprintf('The child with the name "%s" does not exist.', $name));
    }

    /**
     * {@inheritdoc}
     */
    public function remove($name)
    {
        unset($this->unresolvedChildren[$name]);

        if (isset($this->children[$name])) {
            if ($this->children[$name] instanceof self) {
                $this->children[$name]->setParent(null);
            }
            unset($this->children[$name]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        if (isset($this->unresolvedChildren[$name])) {
            return true;
        }

        if (isset($this->children[$name])) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        $this->resolveChildren();

        return $this->children;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->children);
    }

    /**
     * {@inheritdoc}
     */
    public function getForm()
    {
        $this->resolveChildren();

        $form = new Form($this);

        foreach ($this->children as $child) {
            $form->add($child->getForm());
        }

        return $form;
    }

    public function setCurrentLoadingType($type)
    {
        $this->currentLoadingType = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function setParent(FormBuilderInterface $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasParent()
    {
        return null !== $this->parent;
    }

    /**
     * Converts an unresolved child into a {@link FormBuilder} instance.
     *
     * @param  string $name The name of the unresolved child.
     *
     * @return FormBuilder The created instance.
     */
    private function resolveChild($name)
    {
        $info = $this->unresolvedChildren[$name];
        $child = $this->create($name, $info['type'], $info['options']);
        $this->children[$name] = $child;
        unset($this->unresolvedChildren[$name]);

        return $child;
    }

    /**
     * Converts all unresolved children into {@link FormBuilder} instances.
     */
    private function resolveChildren()
    {
        foreach ($this->unresolvedChildren as $name => $info) {
            $this->children[$name] = $this->create($name, $info['type'], $info['options']);
        }

        $this->unresolvedChildren = array();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->children);
    }
}
