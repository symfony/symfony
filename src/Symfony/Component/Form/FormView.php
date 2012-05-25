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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormView implements \IteratorAggregate, FormViewInterface
{
    private $name;

    private $vars = array(
        'value' => null,
        'attr'  => array(),
    );

    private $parent;

    private $children = array();

    /**
     * Is the form attached to this renderer rendered?
     *
     * Rendering happens when either the widget or the row method was called.
     * Row implicitly includes widget, however certain rendering mechanisms
     * have to skip widget rendering when a row is rendered.
     *
     * @var Boolean
     */
    private $rendered = false;

    public function __construct($name)
    {
        $this->name = $name;
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
    public function setVar($name, $value)
    {
        $this->vars[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasVar($name)
    {
        return array_key_exists($name, $this->vars);
    }

    /**
     * {@inheritdoc}
     */
    public function getVar($name, $default = null)
    {
        if (false === $this->hasVar($name)) {
            return $default;
        }

        return $this->vars[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function addVars(array $vars)
    {
        $this->vars = array_replace($this->vars, $vars);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getVars()
    {
        return $this->vars;
    }

    /**
     * Sets the value for an attribute.
     *
     * @param string $name  The name of the attribute
     * @param string $value The value
     *
     * @return FormView The current view
     */
    public function setAttribute($name, $value)
    {
        $this->vars['attr'][$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isRendered()
    {
        $hasChildren = 0 < count($this->children);

        if (true === $this->rendered || !$hasChildren) {
            return $this->rendered;
        }

        if ($hasChildren) {
            foreach ($this->children as $child) {
                if (!$child->isRendered()) {
                    return false;
                }
            }

            return $this->rendered = true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setRendered()
    {
        $this->rendered = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setParent(FormViewInterface $parent = null)
    {
        $this->parent = $parent;

        return $this;
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
    public function hasParent()
    {
        return null !== $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function add(FormViewInterface $child)
    {
        $this->children[$child->getName()] = $child;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($name)
    {
        unset($this->children[$name]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->children;
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (!isset($this->children[$name])) {
            throw new \InvalidArgumentException(sprintf('Child "%s" does not exist.', $name));
        }

        return $this->children[$name];
    }

    /**
     * Returns whether this view has any children.
     *
     * @return Boolean Whether the view has children.
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link count()} instead.
     */
    public function hasChildren()
    {
        return count($this->children) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return isset($this->children[$name]);
    }

    /**
     * Returns a child by name (implements \ArrayAccess).
     *
     * @param string $name The child name
     *
     * @return FormView The child view
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * Returns whether the given child exists (implements \ArrayAccess).
     *
     * @param string $name The child name
     *
     * @return Boolean Whether the child view exists
     */
    public function offsetExists($name)
    {
        return isset($this->children[$name]);
    }

    /**
     * Implements \ArrayAccess.
     *
     * @throws \BadMethodCallException always as setting a child by name is not allowed
     */
    public function offsetSet($name, $value)
    {
        throw new \BadMethodCallException('Not supported');
    }

    /**
     * Removes a child (implements \ArrayAccess).
     *
     * @param string $name The child name
     */
    public function offsetUnset($name)
    {
        unset($this->children[$name]);
    }

    /**
     * Returns an iterator to iterate over children (implements \IteratorAggregate)
     *
     * @return \ArrayIterator The iterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->children);
    }

    /**
     * Implements \Countable.
     *
     * @return integer The number of children views
     */
    public function count()
    {
        return count($this->children);
    }
}
