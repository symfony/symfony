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
 * A form field that can be embedded in a form.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony.com>
 */
interface FieldInterface
{
    /**
     * Sets the parent field.
     *
     * @param FieldInterface $parent  The parent field
     */
    function setParent(FieldInterface $parent = null);

    /**
     * Returns the parent field.
     *
     * @return FieldInterface  The parent field
     */
    function getParent();

    /**
     * Returns the name by which the field is identified in forms.
     *
     * @return string  The name of the field.
     */
    function getName();

    /**
     * Returns the property path of the field
     *
     * @return PropertyPath
     */
    function getPropertyPath();

    /**
     * Adds an error to this field
     *
     * @param Error $error
     */
    function addError(Error $error);

    /**
     * Returns whether the field is valid.
     *
     * @return Boolean
     */
    function isValid();

    /**
     * Returns whether the field is required to be filled out.
     *
     * If the field has a parent and the parent is not required, this method
     * will always return false. Otherwise the value set with setRequired()
     * is returned.
     *
     * @return Boolean
     */
    function isRequired();

    /**
     * Returns whether this field is disabled
     *
     * The content of a disabled field is displayed, but not allowed to be
     * modified. The validation of modified, disabled fields should fail.
     *
     * Fields whose parents are disabled are considered disabled regardless of
     * their own state.
     *
     * @return Boolean
     */
    function isDisabled();

    /**
     * Returns whether the field is empty
     *
     * @return boolean
     */
    function isEmpty();

    /**
     * Writes posted data into the field
     *
     * @param mixed $data  The data from the POST request
     */
    function bind($data);
}
