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
     * Clones this field.
     */
    function __clone();

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
     * Sets the key by which the field is identified in field groups.
     *
     * Once this field is nested in a field group, i.e. after setParent() was
     * called for the first time, this method should throw an exception.
     *
     * @param  string $key             The key of the field
     * @throws BadMethodCallException  When the field already has a parent
     */
    function setKey($key);

    /**
     * Returns the key by which the field is identified in field groups.
     *
     * @return string  The key of the field.
     */
    function getKey();

    /**
     * Returns the name of the field.
     *
     * @return string  When the field has no parent, the name is equal to its
     *                 key. If the field has a parent, the name is composed of
     *                 the parent's name and the field's key, where the field's
     *                 key is wrapped in squared brackets
     *                 (e.g. "parent_name[field_key]")
     */
    function getName();

    /**
     * Returns the ID of the field.
     *
     * @return string  The ID of a field is equal to its name, where all
     *                 sequences of squared brackets are replaced by a single
     *                 underscore (e.g. if the name is "parent_name[field_key]",
     *                 the ID is "parent_name_field_key").
     */
    function getId();

    /**
     * Sets the property path
     *
     * The property path determines the property or a sequence of properties
     * that a field updates in the data of the field group.
     *
     * @param string $propertyPath
     */
    function setPropertyPath($propertyPath);

    /**
     * Returns the property path of the field
     *
     * @return PropertyPath
     */
    function getPropertyPath();

    /**
     * Writes a property value of the object into the field
     *
     * The chosen property is determined by the field's property path.
     *
     * @param array|object $objectOrArray
     */
    function readProperty(&$objectOrArray);

    /**
     * Writes a the field value into a property of the object
     *
     * The chosen property is determined by the field's property path.
     *
     * @param array|object $objectOrArray
     */
    function writeProperty(&$objectOrArray);

    /**
     * Returns the data of the field as it is displayed to the user.
     *
     * @return string|array  When the field is not bound, the transformed
     *                       default data is returned. When the field is bound,
     *                       the bound data is returned.
     */
    function getDisplayedData();

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
     * Returns whether the field requires a multipart form.
     *
     * @return Boolean
     */
    function isMultipart();

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
     * Returns whether the field is hidden
     *
     * @return Boolean
     */
    function isHidden();

    /**
     * Returns whether the field is empty
     *
     * @return boolean
     */
    function isEmpty();

    /**
     * Sets whether this field is required to be filled out when submitted.
     *
     * @param Boolean $required
     */
    function setRequired($required);

    /**
     * Writes posted data into the field
     *
     * @param mixed $data  The data from the POST request
     */
    function submit($data);
}
