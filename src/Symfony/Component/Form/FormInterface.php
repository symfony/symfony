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
     * @param FormInterface $parent  The parent form
     */
    function setParent(FormInterface $parent = null);

    /**
     * Returns the parent form.
     *
     * @return FormInterface  The parent form
     */
    function getParent();

    function add(FormInterface $child);

    function has($name);

    function remove($name);

    function getChildren();

    function hasChildren();

    function hasParent();

    function getErrors();

    function setData($data);

    function getData();

    function getClientData();

    function isBound();

    function getTypes();

    /**
     * Returns the name by which the form is identified in forms.
     *
     * @return string  The name of the form.
     */
    function getName();

    /**
     * Adds an error to this form
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
     * Returns whether this form can be read only
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
     * Returns whether the form is empty
     *
     * @return Boolean
     */
    function isEmpty();

    function isSynchronized();

    /**
     * Writes posted data into the form
     *
     * @param mixed $data  The data from the POST request
     */
    function bind($data);

    function hasAttribute($name);

    function getAttribute($name);

    function getRoot();

    function isRoot();

    function createView(FormView $parent = null);
}
