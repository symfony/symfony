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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

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
     * @return EventDispatcherInterface The dispatcher
     */
    public function getEventDispatcher(): EventDispatcherInterface;

    /**
     * Returns the name of the form used as HTTP parameter.
     *
     * @return string The form name
     */
    public function getName(): string;

    /**
     * Returns the property path that the form should be mapped to.
     *
     * @return null|PropertyPathInterface The property path
     */
    public function getPropertyPath(): ?PropertyPathInterface;

    /**
     * Returns whether the form should be mapped to an element of its
     * parent's data.
     *
     * @return bool Whether the form is mapped
     */
    public function getMapped(): bool;

    /**
     * Returns whether the form's data should be modified by reference.
     *
     * @return bool Whether to modify the form's data by reference
     */
    public function getByReference(): bool;

    /**
     * Returns whether the form should read and write the data of its parent.
     *
     * @return bool Whether the form should inherit its parent's data
     */
    public function getInheritData(): bool;

    /**
     * Returns whether the form is compound.
     *
     * This property is independent of whether the form actually has
     * children. A form can be compound and have no children at all, like
     * for example an empty collection form.
     *
     * @return bool Whether the form is compound
     */
    public function getCompound(): bool;

    /**
     * Returns the form types used to construct the form.
     *
     * @return ResolvedFormTypeInterface The form's type
     */
    public function getType(): ResolvedFormTypeInterface;

    /**
     * Returns the view transformers of the form.
     *
     * @return DataTransformerInterface[] An array of {@link DataTransformerInterface} instances
     */
    public function getViewTransformers();

    /**
     * Returns the model transformers of the form.
     *
     * @return DataTransformerInterface[] An array of {@link DataTransformerInterface} instances
     */
    public function getModelTransformers();

    /**
     * Returns the data mapper of the form.
     *
     * @return DataMapperInterface The data mapper
     */
    public function getDataMapper(): DataMapperInterface;

    /**
     * Returns whether the form is required.
     *
     * @return bool Whether the form is required
     */
    public function getRequired(): bool;

    /**
     * Returns whether the form is disabled.
     *
     * @return bool Whether the form is disabled
     */
    public function getDisabled(): bool;

    /**
     * Returns whether errors attached to the form will bubble to its parent.
     *
     * @return bool Whether errors will bubble up
     */
    public function getErrorBubbling(): bool;

    /**
     * Returns the data that should be returned when the form is empty.
     *
     * @return mixed The data returned if the form is empty
     */
    public function getEmptyData();

    /**
     * Returns additional attributes of the form.
     *
     * @return array An array of key-value combinations
     */
    public function getAttributes(): array;

    /**
     * Returns whether the attribute with the given name exists.
     *
     * @param string $name The attribute name
     *
     * @return bool Whether the attribute exists
     */
    public function hasAttribute(string $name): bool;

    /**
     * Returns the value of the given attribute.
     *
     * @param string $name    The attribute name
     * @param mixed  $default The value returned if the attribute does not exist
     *
     * @return mixed The attribute value
     */
    public function getAttribute(string $name, $default = null);

    /**
     * Returns the initial data of the form.
     *
     * @return mixed The initial form data
     */
    public function getData();

    /**
     * Returns the class of the form data or null if the data is scalar or an array.
     *
     * @return null|string The data class or null
     */
    public function getDataClass(): ?string;

    /**
     * Returns whether the form's data is locked.
     *
     * A form with locked data is restricted to the data passed in
     * this configuration. The data can only be modified then by
     * submitting the form.
     *
     * @return bool Whether the data is locked
     */
    public function getDataLocked(): bool;

    /**
     * Returns the form factory used for creating new forms.
     *
     * @return FormFactoryInterface The form factory
     */
    public function getFormFactory(): FormFactoryInterface;

    /**
     * Returns the target URL of the form.
     *
     * @return string The target URL of the form
     */
    public function getAction(): string;

    /**
     * Returns the HTTP method used by the form.
     *
     * @return string The HTTP method of the form
     */
    public function getMethod(): string;

    /**
     * Returns the request handler used by the form.
     *
     * @return RequestHandlerInterface The request handler
     */
    public function getRequestHandler(): RequestHandlerInterface;

    /**
     * Returns whether the form should be initialized upon creation.
     *
     * @return bool returns true if the form should be initialized
     *              when created, false otherwise
     */
    public function getAutoInitialize(): bool;

    /**
     * Returns all options passed during the construction of the form.
     *
     * @return array The passed options
     */
    public function getOptions(): array;

    /**
     * Returns whether a specific option exists.
     *
     * @param string $name The option name,
     *
     * @return bool Whether the option exists
     */
    public function hasOption(string $name): bool;

    /**
     * Returns the value of a specific option.
     *
     * @param string $name    The option name
     * @param mixed  $default The value returned if the option does not exist
     *
     * @return mixed The option value
     */
    public function getOption(string $name, $default = null);
}
