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
 *
 * @method callable|null getIsEmptyCallback() Returns a callable that takes the model data as argument and that returns if it is empty or not - not implementing it is deprecated since Symfony 5.1
 */
interface FormConfigInterface
{
    /**
     * Returns the event dispatcher used to dispatch form events.
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher();

    /**
     * Returns the name of the form used as HTTP parameter.
     *
     * @return string
     */
    public function getName();

    /**
     * Returns the property path that the form should be mapped to.
     *
     * @return PropertyPathInterface|null
     */
    public function getPropertyPath();

    /**
     * Returns whether the form should be mapped to an element of its
     * parent's data.
     *
     * @return bool
     */
    public function getMapped();

    /**
     * Returns whether the form's data should be modified by reference.
     *
     * @return bool
     */
    public function getByReference();

    /**
     * Returns whether the form should read and write the data of its parent.
     *
     * @return bool
     */
    public function getInheritData();

    /**
     * Returns whether the form is compound.
     *
     * This property is independent of whether the form actually has
     * children. A form can be compound and have no children at all, like
     * for example an empty collection form.
     * The contrary is not possible, a form which is not compound
     * cannot have any children.
     *
     * @return bool
     */
    public function getCompound();

    /**
     * Returns the resolved form type used to construct the form.
     *
     * @return ResolvedFormTypeInterface
     */
    public function getType();

    /**
     * Returns the view transformers of the form.
     *
     * @return DataTransformerInterface[]
     */
    public function getViewTransformers();

    /**
     * Returns the model transformers of the form.
     *
     * @return DataTransformerInterface[]
     */
    public function getModelTransformers();

    /**
     * Returns the data mapper of the compound form or null for a simple form.
     *
     * @return DataMapperInterface|null
     */
    public function getDataMapper();

    /**
     * Returns whether the form is required.
     *
     * @return bool
     */
    public function getRequired();

    /**
     * Returns whether the form is disabled.
     *
     * @return bool
     */
    public function getDisabled();

    /**
     * Returns whether errors attached to the form will bubble to its parent.
     *
     * @return bool
     */
    public function getErrorBubbling();

    /**
     * Used when the view data is empty on submission.
     *
     * When the form is compound it will also be used to map the
     * children data.
     *
     * The empty data must match the view format as it will passed to the first view transformer's
     * "reverseTransform" method.
     *
     * @return mixed
     */
    public function getEmptyData();

    /**
     * Returns additional attributes of the form.
     *
     * @return array
     */
    public function getAttributes();

    /**
     * Returns whether the attribute with the given name exists.
     *
     * @return bool
     */
    public function hasAttribute(string $name);

    /**
     * Returns the value of the given attribute.
     *
     * @param mixed $default The value returned if the attribute does not exist
     *
     * @return mixed
     */
    public function getAttribute(string $name, $default = null);

    /**
     * Returns the initial data of the form.
     *
     * @return mixed
     */
    public function getData();

    /**
     * Returns the class of the view data or null if the data is scalar or an array.
     *
     * @return string|null
     */
    public function getDataClass();

    /**
     * Returns whether the form's data is locked.
     *
     * A form with locked data is restricted to the data passed in
     * this configuration. The data can only be modified then by
     * submitting the form.
     *
     * @return bool
     */
    public function getDataLocked();

    /**
     * Returns the form factory used for creating new forms.
     *
     * @return FormFactoryInterface
     */
    public function getFormFactory();

    /**
     * Returns the target URL of the form.
     *
     * @return string
     */
    public function getAction();

    /**
     * Returns the HTTP method used by the form.
     *
     * @return string
     */
    public function getMethod();

    /**
     * Returns the request handler used by the form.
     *
     * @return RequestHandlerInterface
     */
    public function getRequestHandler();

    /**
     * Returns whether the form should be initialized upon creation.
     *
     * @return bool
     */
    public function getAutoInitialize();

    /**
     * Returns all options passed during the construction of the form.
     *
     * @return array<string, mixed> The passed options
     */
    public function getOptions();

    /**
     * Returns whether a specific option exists.
     *
     * @return bool
     */
    public function hasOption(string $name);

    /**
     * Returns the value of a specific option.
     *
     * @param mixed $default The value returned if the option does not exist
     *
     * @return mixed
     */
    public function getOption(string $name, $default = null);
}
