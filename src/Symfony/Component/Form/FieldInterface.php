<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\I18N\TranslatorInterface;

/**
 * A form field that can be embedded in a form.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface FieldInterface extends Localizable
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
    function updateFromProperty(&$objectOrArray);

    /**
     * Writes a the field value into a property of the object
     *
     * The chosen property is determined by the field's property path.
     *
     * @param array|object $objectOrArray
     */
    function updateProperty(&$objectOrArray);

    /**
     * Returns the normalized data of the field.
     *
     * @return mixed  When the field is not bound, the default data is returned.
     *                When the field is bound, the normalized bound data is
     *                returned if the field is valid, null otherwise.
     */
    function getData();

    /**
     * Returns the data of the field as it is displayed to the user.
     *
     * @return string|array  When the field is not bound, the transformed
     *                       default data is returned. When the field is bound,
     *                       the bound data is returned.
     */
    function getDisplayedData();

    /**
     * Sets the default data
     *
     * @param mixed $default            The default data
     * @throws UnexpectedTypeException  If the default data is invalid
     */
    function setData($default);

    /**
     * Binds POST data to the field, transforms and validates it.
     *
     * @param  string|array $taintedData  The POST data
     * @return boolean                    Whether the form is valid
     * @throws InvalidConfigurationException when the field is not configured
     *                                       correctly
     */
    function bind($taintedData);

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
     * @param FieldError $error
     * @param PropertyPathIterator $pathIterator
     * @param ConstraintViolation$violation
     */
    function addError(FieldError $error, PropertyPathIterator $pathIterator = null, $type = null);

    /**
     * Returns whether the field is bound.
     *
     * @return boolean
     */
    function isBound();

    /**
     * Returns whether the field is valid.
     *
     * @return boolean
     */
    function isValid();

    /**
     * Returns whether the field requires a multipart form.
     *
     * @return boolean
     */
    function isMultipart();

    /**
     * Returns whether the field is required to be filled out.
     *
     * If the field has a parent and the parent is not required, this method
     * will always return false. Otherwise the value set with setRequired()
     * is returned.
     *
     * @return boolean
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
     * @return boolean
     */
    function isDisabled();

    /**
     * Returns whether the field is hidden
     *
     * @return boolean
     */
    function isHidden();

    /**
     * Sets whether this field is required to be filled out when submitted.
     *
     * @param boolean $required
     */
    function setRequired($required);
}
