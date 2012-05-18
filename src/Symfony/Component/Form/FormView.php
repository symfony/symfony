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

class FormView implements \ArrayAccess, \IteratorAggregate, \Countable
{
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

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return FormView The current view
     */
    public function set($name, $value)
    {
        $this->vars[$name] = $value;

        return $this;
    }

    /**
     * @param $name
     *
     * @return Boolean
     */
    public function has($name)
    {
        return array_key_exists($name, $this->vars);
    }

    /**
     * @param $name
     * @param $default
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        if (false === $this->has($name)) {
            return $default;
        }

        return $this->vars[$name];
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->vars;
    }

    /**
     * Alias of all so it is possible to do `form.vars.foo`
     *
     * @return array
     */
    public function getVars()
    {
        return $this->all();
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
     * Returns whether the attached form is rendered.
     *
     * @return Boolean Whether the form is rendered
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
     * Marks the attached form as rendered
     *
     * @return FormView The current view
     */
    public function setRendered()
    {
        $this->rendered = true;

        return $this;
    }

    /**
     * Sets the parent view.
     *
     * @param FormView $parent The parent view
     *
     * @return FormView The current view
     */
    public function setParent(FormView $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Returns the parent view.
     *
     * @return FormView The parent view
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Returns whether this view has a parent.
     *
     * @return Boolean Whether this view has a parent
     */
    public function hasParent()
    {
        return null !== $this->parent;
    }

    /**
     * Sets the children view.
     *
     * @param array $children The children as instances of FormView
     *
     * @return FormView The current view
     */
    public function setChildren(array $children)
    {
        $this->children = $children;

        return $this;
    }

    /**
     * Returns the children.
     *
     * @return array The children as instances of FormView
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Returns a given child.
     *
     * @param string $name The name of the child
     *
     * @return FormView The child view
     */
    public function getChild($name)
    {
        return $this->children[$name];
    }

    /**
     * Returns whether this view has children.
     *
     * @return Boolean Whether this view has children
     */
    public function hasChildren()
    {
        return count($this->children) > 0;
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
        return $this->getChild($name);
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
