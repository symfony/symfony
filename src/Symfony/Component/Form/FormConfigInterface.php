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
 * The configuration of a {@link Form} object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormConfigInterface
{
    /**
     * Returns the event dispatcher used to dispatch form events.
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface The dispatcher.
     */
    function getEventDispatcher();

    /**
     * Returns the name of the form used as HTTP parameter.
     *
     * @return string The form name.
     */
    function getName();

    /**
     * Returns the property path that the form should be mapped to.
     *
     * @return Util\PropertyPath The property path.
     */
    function getPropertyPath();

    /**
     * Returns whether the form should be mapped to an element of its
     * parent's data.
     *
     * @return Boolean Whether the form is mapped.
     */
    function getMapped();

    /**
     * Returns whether the form's data should be modified by reference.
     *
     * @return Boolean Whether to modify the form's data by reference.
     */
    function getByReference();

    /**
     * Returns whether the form should be virtual.
     *
     * When mapping data to the children of a form, the data mapper
     * should ignore virtual forms and map to the children of the
     * virtual form instead.
     *
     * @return Boolean Whether the form is virtual.
     */
    function getVirtual();

    /**
     * Returns the form types used to construct the form.
     *
     * @return array An array of {@link FormTypeInterface} instances.
     */
    function getTypes();

    /**
     * Returns the view transformers of the form.
     *
     * @return array An array of {@link DataTransformerInterface} instances.
     */
    function getViewTransformers();

    /**
     * Returns the model transformers of the form.
     *
     * @return array An array of {@link DataTransformerInterface} instances.
     */
    function getModelTransformers();

    /**
     * Returns the data mapper of the form.
     *
     * @return DataMapperInterface The data mapper.
     */
    function getDataMapper();

    /**
     * Returns the validators of the form.
     *
     * @return FormValidatorInterface The form validator.
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    function getValidators();

    /**
     * Returns whether the form is required.
     *
     * @return Boolean Whether the form is required.
     */
    function getRequired();

    /**
     * Returns whether the form is disabled.
     *
     * @return Boolean Whether the form is disabled.
     */
    function getDisabled();

    /**
     * Returns whether errors attached to the form will bubble to its parent.
     *
     * @return Boolean Whether errors will bubble up.
     */
    function getErrorBubbling();

    /**
     * Returns the data that should be returned when the form is empty.
     *
     * @return mixed The data returned if the form is empty.
     */
    function getEmptyData();

    /**
     * Returns additional attributes of the form.
     *
     * @return array An array of key-value combinations.
     */
    function getAttributes();

    /**
     * Returns whether the attribute with the given name exists.
     *
     * @param  string $name The attribute name.
     *
     * @return Boolean Whether the attribute exists.
     */
    function hasAttribute($name);

    /**
     * Returns the value of the given attribute.
     *
     * @param  string $name    The attribute name.
     * @param  mixed  $default The value returned if the attribute does not exist.
     *
     * @return mixed The attribute value.
     */
    function getAttribute($name, $default = null);

    /**
     * Returns the initial data of the form.
     *
     * @return mixed The initial form data.
     */
    function getData();

    /**
     * Returns the class of the form data or null if the data is scalar or an array.
     *
     * @return string The data class or null.
     */
    function getDataClass();

    /**
     * Returns all options passed during the construction of the form.
     *
     * @return array The passed options.
     */
    function getOptions();

    /**
     * Returns whether a specific option exists.
     *
     * @param  string $name The option name,
     *
     * @return Boolean Whether the option exists.
     */
    function hasOption($name);

    /**
     * Returns the value of a specific option.
     *
     * @param  string $name    The option name.
     * @param  mixed  $default The value returned if the option does not exist.
     *
     * @return mixed The option value.
     */
    function getOption($name, $default = null);
}
