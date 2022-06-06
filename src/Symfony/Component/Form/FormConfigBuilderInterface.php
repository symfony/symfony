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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @method $this setIsEmptyCallback(callable|null $isEmptyCallback) Sets the callback that will be called to determine if the model data of the form is empty or not - not implementing it is deprecated since Symfony 5.1
 */
interface FormConfigBuilderInterface extends FormConfigInterface
{
    /**
     * Adds an event listener to an event on this form.
     *
     * @param int $priority The priority of the listener. Listeners
     *                      with a higher priority are called before
     *                      listeners with a lower priority.
     *
     * @return $this
     */
    public function addEventListener(string $eventName, callable $listener, int $priority = 0);

    /**
     * Adds an event subscriber for events on this form.
     *
     * @return $this
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber);

    /**
     * Appends / prepends a transformer to the view transformer chain.
     *
     * The transform method of the transformer is used to convert data from the
     * normalized to the view format.
     * The reverseTransform method of the transformer is used to convert from the
     * view to the normalized format.
     *
     * @param bool $forcePrepend If set to true, prepend instead of appending
     *
     * @return $this
     */
    public function addViewTransformer(DataTransformerInterface $viewTransformer, bool $forcePrepend = false);

    /**
     * Clears the view transformers.
     *
     * @return $this
     */
    public function resetViewTransformers();

    /**
     * Prepends / appends a transformer to the normalization transformer chain.
     *
     * The transform method of the transformer is used to convert data from the
     * model to the normalized format.
     * The reverseTransform method of the transformer is used to convert from the
     * normalized to the model format.
     *
     * @param bool $forceAppend If set to true, append instead of prepending
     *
     * @return $this
     */
    public function addModelTransformer(DataTransformerInterface $modelTransformer, bool $forceAppend = false);

    /**
     * Clears the normalization transformers.
     *
     * @return $this
     */
    public function resetModelTransformers();

    /**
     * Sets the value for an attribute.
     *
     * @param mixed $value The value of the attribute
     *
     * @return $this
     */
    public function setAttribute(string $name, $value);

    /**
     * Sets the attributes.
     *
     * @return $this
     */
    public function setAttributes(array $attributes);

    /**
     * Sets the data mapper used by the form.
     *
     * @return $this
     */
    public function setDataMapper(DataMapperInterface $dataMapper = null);

    /**
     * Sets whether the form is disabled.
     *
     * @return $this
     */
    public function setDisabled(bool $disabled);

    /**
     * Sets the data used for the client data when no value is submitted.
     *
     * @param mixed $emptyData The empty data
     *
     * @return $this
     */
    public function setEmptyData($emptyData);

    /**
     * Sets whether errors bubble up to the parent.
     *
     * @return $this
     */
    public function setErrorBubbling(bool $errorBubbling);

    /**
     * Sets whether this field is required to be filled out when submitted.
     *
     * @return $this
     */
    public function setRequired(bool $required);

    /**
     * Sets the property path that the form should be mapped to.
     *
     * @param string|PropertyPathInterface|null $propertyPath The property path or null if the path should be set
     *                                                        automatically based on the form's name
     *
     * @return $this
     */
    public function setPropertyPath($propertyPath);

    /**
     * Sets whether the form should be mapped to an element of its
     * parent's data.
     *
     * @return $this
     */
    public function setMapped(bool $mapped);

    /**
     * Sets whether the form's data should be modified by reference.
     *
     * @return $this
     */
    public function setByReference(bool $byReference);

    /**
     * Sets whether the form should read and write the data of its parent.
     *
     * @return $this
     */
    public function setInheritData(bool $inheritData);

    /**
     * Sets whether the form should be compound.
     *
     * @return $this
     *
     * @see FormConfigInterface::getCompound()
     */
    public function setCompound(bool $compound);

    /**
     * Sets the resolved type.
     *
     * @return $this
     */
    public function setType(ResolvedFormTypeInterface $type);

    /**
     * Sets the initial data of the form.
     *
     * @param mixed $data The data of the form in model format
     *
     * @return $this
     */
    public function setData($data);

    /**
     * Locks the form's data to the data passed in the configuration.
     *
     * A form with locked data is restricted to the data passed in
     * this configuration. The data can only be modified then by
     * submitting the form or using PRE_SET_DATA event.
     *
     * It means data passed to a factory method or mapped from the
     * parent will be ignored.
     *
     * @return $this
     */
    public function setDataLocked(bool $locked);

    /**
     * Sets the form factory used for creating new forms.
     */
    public function setFormFactory(FormFactoryInterface $formFactory);

    /**
     * Sets the target URL of the form.
     *
     * @return $this
     */
    public function setAction(string $action);

    /**
     * Sets the HTTP method used by the form.
     *
     * @return $this
     */
    public function setMethod(string $method);

    /**
     * Sets the request handler used by the form.
     *
     * @return $this
     */
    public function setRequestHandler(RequestHandlerInterface $requestHandler);

    /**
     * Sets whether the form should be initialized automatically.
     *
     * Should be set to true only for root forms.
     *
     * @param bool $initialize True to initialize the form automatically,
     *                         false to suppress automatic initialization.
     *                         In the second case, you need to call
     *                         {@link FormInterface::initialize()} manually.
     *
     * @return $this
     */
    public function setAutoInitialize(bool $initialize);

    /**
     * Builds and returns the form configuration.
     *
     * @return FormConfigInterface
     */
    public function getFormConfig();
}
