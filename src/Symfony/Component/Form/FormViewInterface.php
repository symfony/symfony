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
interface FormViewInterface extends \ArrayAccess, \Traversable, \Countable
{
    /**
     * Returns the name of the form.
     *
     * @return string The form name.
     */
    function getName();

    /**
     * Sets a view variable.
     *
     * @param string $name  The variable name.
     * @param string $value The variable value.
     *
     * @return FormViewInterface The view object.
     */
    function set($name, $value);

    /**
     * Returns whether a view variable exists.
     *
     * @param string $name The variable name.
     *
     * @return Boolean Whether the variable exists.
     */
    function has($name);

    /**
     * Returns the value of a view variable.
     *
     * @param string $name    The variable name.
     * @param mixed  $default The value to return if the variable is not set.
     *
     * @return mixed The variable value.
     */
    function get($name, $default = null);

    /**
     * Returns the values of all view variables.
     *
     * @return array The values of all variables.
     */
    function all();

    /**
     * Returns whether the view was already rendered.
     *
     * @return Boolean Whether this view's widget is rendered.
     */
    function isRendered();

    /**
     * Marks the view as rendered.
     *
     * @return FormViewInterface The view object.
     */
    function setRendered();

    /**
     * Sets the parent view.
     *
     * @param FormViewInterface $parent The parent view.
     *
     * @return FormViewInterface The view object.
     */
    function setParent(FormViewInterface $parent = null);

    /**
     * Returns the parent view.
     *
     * @return FormViewInterface The parent view.
     */
    function getParent();

    /**
     * Returns whether this view has a parent.
     *
     * @return Boolean Whether this view has a parent
     */
    function hasParent();

    /**
     * Adds a child view.
     *
     * @param FormViewInterface $child The child view to add.
     *
     * @return FormViewInterface The view object.
     */
    function addChild(FormViewInterface $child);

    /**
     * Removes a child view.
     *
     * @param string $name The name of the removed child view.
     *
     * @return FormViewInterface The view object.
     */
    function removeChild($name);

    /**
     * Returns the children.
     *
     * @return array The children as instances of FormView
     */
    function getChildren();

    /**
     * Returns a given child.
     *
     * @param string $name The name of the child
     *
     * @return FormViewInterface The child view
     */
    function getChild($name);

    /**
     * Returns whether this view has children.
     *
     * @return Boolean Whether this view has children
     */
    function hasChildren();

    /**
     * Returns whether this view has a given child.
     *
     * @param string $name The name of the child
     *
     * @return Boolean Whether the child with the given name exists
     */
    function hasChild($name);
}
