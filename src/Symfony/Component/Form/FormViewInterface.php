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
    function add(FormViewInterface $child);

    /**
     * Removes a child view.
     *
     * @param string $name The name of the removed child view.
     *
     * @return FormViewInterface The view object.
     */
    function remove($name);

    /**
     * Returns the children.
     *
     * @return array The children as instances of FormView
     */
    function all();

    /**
     * Returns a given child.
     *
     * @param string $name The name of the child
     *
     * @return FormViewInterface The child view
     */
    function get($name);

    /**
     * Returns whether this view has a given child.
     *
     * @param string $name The name of the child
     *
     * @return Boolean Whether the child with the given name exists
     */
    function has($name);

    /**
     * Sets a view variable.
     *
     * @param string $name  The variable name.
     * @param string $value The variable value.
     *
     * @return FormViewInterface The view object.
     */
    function setVar($name, $value);

    /**
     * Adds a list of view variables.
     *
     * @param array $values An array of variable names and values.
     *
     * @return FormViewInterface The view object.
     */
    function addVars(array $values);

    /**
     * Returns whether a view variable exists.
     *
     * @param string $name The variable name.
     *
     * @return Boolean Whether the variable exists.
     */
    function hasVar($name);

    /**
     * Returns the value of a view variable.
     *
     * @param string $name    The variable name.
     * @param mixed  $default The value to return if the variable is not set.
     *
     * @return mixed The variable value.
     */
    function getVar($name, $default = null);

    /**
     * Returns the values of all view variables.
     *
     * @return array The values of all variables.
     */
    function getVars();
}
