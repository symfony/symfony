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
class FormView implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * The variables assigned to this view.
     * @var array
     */
    public $vars = array(
        'value' => null,
        'attr'  => array(),
    );

    /**
     * The parent view.
     * @var FormView
     */
    public $parent;

    /**
     * The child views.
     * @var array
     */
    public $children = array();

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

    public function __construct(FormView $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * Returns the name of the form.
     *
     * @return string The form name.
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link vars} instead which contains an
     *             entry named "name".
     */
    public function getName()
    {
        trigger_error('getName() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'vars\' instead which contains an entry named "name".', E_USER_DEPRECATED);

        return $this->vars['name'];
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return FormView The current view
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link vars} instead.
     */
    public function set($name, $value)
    {
        trigger_error('set() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'vars\' instead.', E_USER_DEPRECATED);

        $this->vars[$name] = $value;

        return $this;
    }

    /**
     * @param $name
     *
     * @return Boolean
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link vars} instead.
     */
    public function has($name)
    {
        trigger_error('has() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'vars\' instead.', E_USER_DEPRECATED);

        return array_key_exists($name, $this->vars);
    }

    /**
     * @param $name
     * @param $default
     *
     * @return mixed
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link vars} instead.
     */
    public function get($name, $default = null)
    {
        trigger_error('get() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'vars\' instead.', E_USER_DEPRECATED);

        if (false === $this->has($name)) {
            return $default;
        }

        return $this->vars[$name];
    }

    /**
     * @return array
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link vars} instead.
     */
    public function all()
    {
        trigger_error('all() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'vars\' instead.', E_USER_DEPRECATED);

        return $this->vars;
    }

    /**
     * Returns the values of all view variables.
     *
     * @return array The values of all variables.
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link vars} instead.
     */
    public function getVars()
    {
        trigger_error('getVars() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'vars\' instead.', E_USER_DEPRECATED);

        return $this->vars;
    }

    /**
     * Sets the value for an attribute.
     *
     * @param string $name  The name of the attribute
     * @param string $value The value
     *
     * @return FormView The current view
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link vars} instead which contains an
     *             entry named "attr".
     */
    public function setAttribute($name, $value)
    {
        trigger_error('setAttribute() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'vars\' instead which contains an entry named "attr".', E_USER_DEPRECATED);

        $this->vars['attr'][$name] = $value;

        return $this;
    }

    /**
     * Returns whether the view was already rendered.
     *
     * @return Boolean Whether this view's widget is rendered.
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
     * Marks the view as rendered.
     *
     * @return FormView The view object.
     */
    public function setRendered()
    {
        $this->rendered = true;

        return $this;
    }

    /**
     * Sets the parent view.
     *
     * @param FormView $parent The parent view.
     *
     * @return FormView The view object.
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link parent} instead.
     */
    public function setParent(FormView $parent = null)
    {
        trigger_error('setParent() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'parent\' instead.', E_USER_DEPRECATED);

        $this->parent = $parent;

        return $this;
    }

    /**
     * Returns the parent view.
     *
     * @return FormView The parent view.
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link parent} instead.
     */
    public function getParent()
    {
        trigger_error('getParent() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'parent\' instead.', E_USER_DEPRECATED);

        return $this->parent;
    }

    /**
     * Returns whether this view has a parent.
     *
     * @return Boolean Whether this view has a parent
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link parent} instead.
     */
    public function hasParent()
    {
        trigger_error('hasParent() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'parent\' instead.', E_USER_DEPRECATED);

        return null !== $this->parent;
    }

    /**
     * Sets the children view.
     *
     * @param array $children The children as instances of FormView
     *
     * @return FormView The current view
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link children} instead.
     */
    public function setChildren(array $children)
    {
        trigger_error('setChildren() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'children\' instead.', E_USER_DEPRECATED);

        $this->children = $children;

        return $this;
    }

    /**
     * Returns the children.
     *
     * @return array The children as instances of FormView
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link children} instead.
     */
    public function getChildren()
    {
        trigger_error('getChildren() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'children\' instead.', E_USER_DEPRECATED);

        return $this->children;
    }

    /**
     * Returns a given child.
     *
     * @param string $name The name of the child
     *
     * @return FormView The child view
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Access
     *             the public property {@link children} instead.
     */
    public function getChild($name)
    {
        trigger_error('getChild() is deprecated since version 2.1 and will be removed in 2.3. Access the public property \'children\' instead.', E_USER_DEPRECATED);

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
        trigger_error('hasChildren() is deprecated since version 2.1 and will be removed in 2.3. Use count() instead.', E_USER_DEPRECATED);

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
        return $this->children[$name];
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
