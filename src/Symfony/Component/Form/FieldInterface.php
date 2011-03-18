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

use Symfony\Component\Translation\TranslatorInterface;

/**
 * A form field that can be embedded in a form.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony.com>
 */
interface FieldInterface
{
    /**
     * Marks a constraint violation in a form field
     * @var integer
     */
    const FIELD_ERROR = 0;

    /**
     * Marks a constraint violation in the data of a form field
     * @var integer
     */
    const DATA_ERROR = 1;

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
     * Recursively adds constraint violations to the fields
     *
     * Violations in the form fields usually have property paths like:
     *
     * <code>
     * iterator[firstName].data
     * iterator[firstName].displayedData
     * iterator[Address].iterator[street].displayedData
     * ...
     * </code>
     *
     * Violations in the form data usually have property paths like:
     *
     * <code>
     * data.firstName
     * data.Address.street
     * ...
     * </code>
     *
     * @param Error $error
     * @param PropertyPathIterator $pathIterator
     */
    function addError(Error $error, PropertyPathIterator $pathIterator = null);

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
