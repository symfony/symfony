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
     *
     * @since v2.1.0
     */
    public function getEventDispatcher();

    /**
     * Returns the name of the form used as HTTP parameter.
     *
     * @return string The form name.
     *
     * @since v2.1.0
     */
    public function getName();

    /**
     * Returns the property path that the form should be mapped to.
     *
     * @return null|\Symfony\Component\PropertyAccess\PropertyPathInterface The property path.
     *
     * @since v2.1.0
     */
    public function getPropertyPath();

    /**
     * Returns whether the form should be mapped to an element of its
     * parent's data.
     *
     * @return Boolean Whether the form is mapped.
     *
     * @since v2.1.0
     */
    public function getMapped();

    /**
     * Returns whether the form's data should be modified by reference.
     *
     * @return Boolean Whether to modify the form's data by reference.
     *
     * @since v2.1.0
     */
    public function getByReference();

    /**
     * Returns whether the form should read and write the data of its parent.
     *
     * @return Boolean Whether the form should inherit its parent's data.
     *
     * @since v2.3.0
     */
    public function getInheritData();

    /**
     * Returns whether the form is compound.
     *
     * This property is independent of whether the form actually has
     * children. A form can be compound and have no children at all, like
     * for example an empty collection form.
     *
     * @return Boolean Whether the form is compound.
     *
     * @since v2.1.0
     */
    public function getCompound();

    /**
     * Returns the form types used to construct the form.
     *
     * @return ResolvedFormTypeInterface The form's type.
     *
     * @since v2.1.0
     */
    public function getType();

    /**
     * Returns the view transformers of the form.
     *
     * @return DataTransformerInterface[] An array of {@link DataTransformerInterface} instances.
     *
     * @since v2.1.0
     */
    public function getViewTransformers();

    /**
     * Returns the model transformers of the form.
     *
     * @return DataTransformerInterface[] An array of {@link DataTransformerInterface} instances.
     *
     * @since v2.1.0
     */
    public function getModelTransformers();

    /**
     * Returns the data mapper of the form.
     *
     * @return DataMapperInterface The data mapper.
     *
     * @since v2.1.0
     */
    public function getDataMapper();

    /**
     * Returns whether the form is required.
     *
     * @return Boolean Whether the form is required.
     *
     * @since v2.1.0
     */
    public function getRequired();

    /**
     * Returns whether the form is disabled.
     *
     * @return Boolean Whether the form is disabled.
     *
     * @since v2.1.0
     */
    public function getDisabled();

    /**
     * Returns whether errors attached to the form will bubble to its parent.
     *
     * @return Boolean Whether errors will bubble up.
     *
     * @since v2.1.0
     */
    public function getErrorBubbling();

    /**
     * Returns the data that should be returned when the form is empty.
     *
     * @return mixed The data returned if the form is empty.
     *
     * @since v2.1.0
     */
    public function getEmptyData();

    /**
     * Returns additional attributes of the form.
     *
     * @return array An array of key-value combinations.
     *
     * @since v2.1.0
     */
    public function getAttributes();

    /**
     * Returns whether the attribute with the given name exists.
     *
     * @param  string $name The attribute name.
     *
     * @return Boolean Whether the attribute exists.
     *
     * @since v2.1.0
     */
    public function hasAttribute($name);

    /**
     * Returns the value of the given attribute.
     *
     * @param  string $name    The attribute name.
     * @param  mixed  $default The value returned if the attribute does not exist.
     *
     * @return mixed The attribute value.
     *
     * @since v2.1.0
     */
    public function getAttribute($name, $default = null);

    /**
     * Returns the initial data of the form.
     *
     * @return mixed The initial form data.
     *
     * @since v2.1.0
     */
    public function getData();

    /**
     * Returns the class of the form data or null if the data is scalar or an array.
     *
     * @return string The data class or null.
     *
     * @since v2.1.0
     */
    public function getDataClass();

    /**
     * Returns whether the form's data is locked.
     *
     * A form with locked data is restricted to the data passed in
     * this configuration. The data can only be modified then by
     * submitting the form.
     *
     * @return Boolean Whether the data is locked.
     *
     * @since v2.1.0
     */
    public function getDataLocked();

    /**
     * Returns the form factory used for creating new forms.
     *
     * @return FormFactoryInterface The form factory.
     *
     * @since v2.2.0
     */
    public function getFormFactory();

    /**
     * Returns the target URL of the form.
     *
     * @return string The target URL of the form.
     *
     * @since v2.3.0
     */
    public function getAction();

    /**
     * Returns the HTTP method used by the form.
     *
     * @return string The HTTP method of the form.
     *
     * @since v2.3.0
     */
    public function getMethod();

    /**
     * Returns the request handler used by the form.
     *
     * @return RequestHandlerInterface The request handler.
     *
     * @since v2.3.0
     */
    public function getRequestHandler();

    /**
     * Returns whether the form should be initialized upon creation.
     *
     * @return Boolean Returns true if the form should be initialized
     *                 when created, false otherwise.
     *
     * @since v2.3.0
     */
    public function getAutoInitialize();

    /**
     * Returns all options passed during the construction of the form.
     *
     * @return array The passed options.
     *
     * @since v2.1.0
     */
    public function getOptions();

    /**
     * Returns whether a specific option exists.
     *
     * @param  string $name The option name,
     *
     * @return Boolean Whether the option exists.
     *
     * @since v2.1.0
     */
    public function hasOption($name);

    /**
     * Returns the value of a specific option.
     *
     * @param  string $name    The option name.
     * @param  mixed  $default The value returned if the option does not exist.
     *
     * @return mixed The option value.
     *
     * @since v2.1.0
     */
    public function getOption($name, $default = null);
}
