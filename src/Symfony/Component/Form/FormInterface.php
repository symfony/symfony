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
 * A form group bundling multiple form forms
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
interface FormInterface extends \ArrayAccess, \Traversable, \Countable
{
    /**
     * Sets the parent form.
     *
     * @param FormInterface $parent The parent form
     */
    function setParent(FormInterface $parent = null);

    /**
     * Returns the parent form.
     *
     * @return FormInterface The parent form
     */
    function getParent();

    /**
     * Returns whether the form has a parent.
     *
     * @return Boolean
     */
    function hasParent();

    /**
     * Adds a child to the form.
     *
     * @param FormInterface $child The FormInterface to add as a child
     */
    function add(FormInterface $child);

    /**
     * Returns whether a child with the given name exists.
     *
     * @param string $name
     *
     * @return Boolean
     */
    function has($name);

    /**
     * Removes a child from the form.
     *
     * @param string $name The name of the child to remove
     */
    function remove($name);

    /**
     * Returns all children in this group.
     *
     * @return array An array of FormInterface instances
     */
    function getChildren();

    /**
     * Return whether the form has children.
     *
     * @return Boolean
     */
    function hasChildren();

    /**
     * Returns all errors.
     *
     * @return array An array of FormError instances that occurred during binding
     */
    function getErrors();

    /**
     * Updates the field with default data.
     *
     * @param array $appData The data formatted as expected for the underlying object
     *
     * @return Form The current form
     */
    function setData($appData);

    /**
     * Returns the data in the format needed for the underlying object.
     *
     * @return mixed
     */
    function getData();

    /**
     * Returns the normalized data of the field.
     *
     * @return mixed  When the field is not bound, the default data is returned.
     *                When the field is bound, the normalized bound data is
     *                returned if the field is valid, null otherwise.
     */
    function getNormData();

    /**
     * Returns the data transformed by the value transformer.
     *
     * @return string
     */
    function getClientData();

    /**
     * Returns the extra data.
     *
     * @return array The bound data which do not belong to a child
     */
    function getExtraData();

    /**
     * Returns whether the field is bound.
     *
     * @return Boolean true if the form is bound to input values, false otherwise
     */
    function isBound();

    /**
     * Returns the supported types.
     *
     * @return array An array of FormTypeInterface
     */
    function getTypes();

    /**
     * Returns the name by which the form is identified in forms.
     *
     * @return string  The name of the form.
     */
    function getName();

    /**
     * Adds an error to this form.
     *
     * @param FormError $error
     */
    function addError(FormError $error);

    /**
     * Returns whether the form is valid.
     *
     * @return Boolean
     */
    function isValid();

    /**
     * Returns whether the form is required to be filled out.
     *
     * If the form has a parent and the parent is not required, this method
     * will always return false. Otherwise the value set with setRequired()
     * is returned.
     *
     * @return Boolean
     */
    function isRequired();

    /**
     * Returns whether this form can be read only.
     *
     * The content of a read-only form is displayed, but not allowed to be
     * modified. The validation of modified read-only forms should fail.
     *
     * Fields whose parents are read-only are considered read-only regardless of
     * their own state.
     *
     * @return Boolean
     */
    function isReadOnly();

    /**
     * Returns whether the form is empty.
     *
     * @return Boolean
     */
    function isEmpty();

    /**
     * Returns whether the data in the different formats is synchronized.
     *
     * @return Boolean
     */
    function isSynchronized();

    /**
     * Writes data into the form.
     *
     * @param mixed $data  The data
     */
    function bind($data);

    /**
     * Returns whether the form has an attribute with the given name.
     *
     * @param string $name The name of the attribute
     */
    function hasAttribute($name);

    /**
     * Returns the value of the attributes with the given name.
     *
     * @param string $name The name of the attribute
     */
    function getAttribute($name);

    /**
     * Returns the root of the form tree.
     *
     * @return FormInterface  The root of the tree
     */
    function getRoot();

    /**
     * Returns whether the field is the root of the form tree.
     *
     * @return Boolean
     */
    function isRoot();

    /**
     * Creates a view.
     *
     * @param FormView $parent The parent view
     *
     * @return FormView The view
     */
    function createView(FormView $parent = null);
}
