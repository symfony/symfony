<?php

namespace Symfony\Components\Form;

use Symfony\Components\I18N\TranslatorInterface;

/**
 * A form field that can be embedded in a form.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: FieldInterface.php 247 2010-02-01 09:24:55Z bernhard $
 */
interface FieldInterface extends Localizable, Translatable
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
    public function __clone();

    /**
     * Sets the parent field.
     *
     * @param FieldInterface $parent  The parent field
     */
    public function setParent(FieldInterface $parent = null);

    /**
     * Sets the key by which the field is identified in field groups.
     *
     * Once this field is nested in a field group, i.e. after setParent() was
     * called for the first time, this method should throw an exception.
     *
     * @param  string $key             The key of the field
     * @throws BadMethodCallException  When the field already has a parent
     */
    public function setKey($key);

    /**
     * Returns the key by which the field is identified in field groups.
     *
     * @return string  The key of the field.
     */
    public function getKey();

    /**
     * Returns the name of the field.
     *
     * @return string  When the field has no parent, the name is equal to its
     *                 key. If the field has a parent, the name is composed of
     *                 the parent's name and the field's key, where the field's
     *                 key is wrapped in squared brackets
     *                 (e.g. "parent_name[field_key]")
     */
    public function getName();

    /**
     * Returns the ID of the field.
     *
     * @return string  The ID of a field is equal to its name, where all
     *                 sequences of squared brackets are replaced by a single
     *                 underscore (e.g. if the name is "parent_name[field_key]",
     *                 the ID is "parent_name_field_key").
     */
    public function getId();

    /**
     * Sets the property path
     *
     * The property path determines the property or a sequence of properties
     * that a field updates in the data of the field group.
     *
     * @param string $propertyPath
     */
    public function setPropertyPath($propertyPath);

    /**
     * Returns the property path of the field
     *
     * @return PropertyPath
     */
    public function getPropertyPath();

    /**
     * Writes a property value of the object into the field
     *
     * The chosen property is determined by the field's property path.
     *
     * @param array|object $objectOrArray
     */
    public function updateFromObject(&$objectOrArray);

    /**
     * Writes a the field value into a property of the object
     *
     * The chosen property is determined by the field's property path.
     *
     * @param array|object $objectOrArray
     */
    public function updateObject(&$objectOrArray);

    /**
     * Returns the normalized data of the field.
     *
     * @return mixed  When the field is not bound, the default data is returned.
     *                When the field is bound, the normalized bound data is
     *                returned if the field is valid, null otherwise.
     */
    public function getData();

    /**
     * Returns the data of the field as it is displayed to the user.
     *
     * @return string|array  When the field is not bound, the transformed
     *                       default data is returned. When the field is bound,
     *                       the bound data is returned.
     */
    public function getDisplayedData();

    /**
     * Sets the default data
     *
     * @param mixed $default            The default data
     * @throws UnexpectedTypeException  If the default data is invalid
     */
    public function setData($default);

    /**
     * Binds POST data to the field, transforms and validates it.
     *
     * @param  string|array $taintedData  The POST data
     * @return boolean                    Whether the form is valid
     * @throws InvalidConfigurationException when the field is not configured
     *                                       correctly
     */
    public function bind($taintedData);

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
     * @param FieldInterface $field
     * @param PropertyPath $path
     * @param ConstraintViolation$violation
     */
    public function addError($message, PropertyPath $path = null, $type = null);

    /**
     * Renders this field.
     *
     * @param  array $attributes  The attributes to include in the rendered
     *                            output
     * @return string             The rendered output of this field
     */
    public function render(array $attributes = array());

    /**
     * Renders the errors of this field.
     *
     * @return string  The rendered output of the field errors
     */
    public function renderErrors();

    /**
     * Returns whether the field is bound.
     *
     * @return boolean
     */
    public function isBound();

    /**
     * Returns whether the field is valid.
     *
     * @return boolean
     */
    public function isValid();

    /**
     * Returns whether the field requires a multipart form.
     *
     * @return boolean
     */
    public function isMultipart();

    /**
     * Returns whether the field is required to be filled out.
     *
     * If the field has a parent and the parent is not required, this method
     * will always return false. Otherwise the value set with setRequired()
     * is returned.
     *
     * @return boolean
     */
    public function isRequired();

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
    public function isDisabled();

    /**
     * Returns whether the field is hidden
     *
     * @return boolean
     */
    public function isHidden();

    /**
     * Sets whether this field is required to be filled out when submitted.
     *
     * @param boolean $required
     */
    public function setRequired($required);

    /**
     * Sets the generator used for rendering HTML.
     *
     * Usually there is one generator instance shared between all fields of a
     * form.
     *
     * @param string $charset
     */
    public function setGenerator(HtmlGeneratorInterface $generator);
}