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
 */
interface FormConfigBuilderInterface extends FormConfigInterface
{
    /**
     * Adds an event listener to an event on this form.
     *
     * @param string   $eventName the name of the event to listen to.
     * @param callable $listener  the listener to execute.
     * @param int      $priority  the priority of the listener. Listeners
     *                            with a higher priority are called before
     *                            listeners with a lower priority.
     *
     * @return $this the configuration object.
     */
    public function addEventListener($eventName, $listener, $priority = 0);

    /**
     * Adds an event subscriber for events on this form.
     *
     * @param EventSubscriberInterface $subscriber the subscriber to attach.
     *
     * @return $this the configuration object.
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
     * @param DataTransformerInterface $viewTransformer
     * @param bool                     $forcePrepend    if set to true, prepend instead of appending
     *
     * @return $this the configuration object.
     */
    public function addViewTransformer(DataTransformerInterface $viewTransformer, $forcePrepend = false);

    /**
     * Clears the view transformers.
     *
     * @return $this the configuration object.
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
     * @param DataTransformerInterface $modelTransformer
     * @param bool                     $forceAppend      if set to true, append instead of prepending
     *
     * @return $this the configuration object.
     */
    public function addModelTransformer(DataTransformerInterface $modelTransformer, $forceAppend = false);

    /**
     * Clears the normalization transformers.
     *
     * @return $this the configuration object.
     */
    public function resetModelTransformers();

    /**
     * Sets the value for an attribute.
     *
     * @param string $name  the name of the attribute
     * @param mixed  $value the value of the attribute
     *
     * @return $this the configuration object.
     */
    public function setAttribute($name, $value);

    /**
     * Sets the attributes.
     *
     * @param array $attributes the attributes.
     *
     * @return $this the configuration object.
     */
    public function setAttributes(array $attributes);

    /**
     * Sets the data mapper used by the form.
     *
     * @param DataMapperInterface $dataMapper
     *
     * @return $this the configuration object.
     */
    public function setDataMapper(DataMapperInterface $dataMapper = null);

    /**
     * Set whether the form is disabled.
     *
     * @param bool $disabled whether the form is disabled
     *
     * @return $this the configuration object.
     */
    public function setDisabled($disabled);

    /**
     * Sets the data used for the client data when no value is submitted.
     *
     * @param mixed $emptyData the empty data.
     *
     * @return $this the configuration object.
     */
    public function setEmptyData($emptyData);

    /**
     * Sets whether errors bubble up to the parent.
     *
     * @param bool $errorBubbling
     *
     * @return $this the configuration object.
     */
    public function setErrorBubbling($errorBubbling);

    /**
     * Sets whether this field is required to be filled out when submitted.
     *
     * @param bool $required
     *
     * @return $this the configuration object.
     */
    public function setRequired($required);

    /**
     * Sets the property path that the form should be mapped to.
     *
     * @param null|string|PropertyPathInterface $propertyPath
     *                                                        The property path or null if the path should be set
     *                                                        automatically based on the form's name.
     *
     * @return $this the configuration object.
     */
    public function setPropertyPath($propertyPath);

    /**
     * Sets whether the form should be mapped to an element of its
     * parent's data.
     *
     * @param bool $mapped whether the form should be mapped.
     *
     * @return $this the configuration object.
     */
    public function setMapped($mapped);

    /**
     * Sets whether the form's data should be modified by reference.
     *
     * @param bool $byReference whether the data should be
     *                          modified by reference.
     *
     * @return $this the configuration object.
     */
    public function setByReference($byReference);

    /**
     * Sets whether the form should read and write the data of its parent.
     *
     * @param bool $inheritData whether the form should inherit its parent's data.
     *
     * @return $this the configuration object.
     */
    public function setInheritData($inheritData);

    /**
     * Sets whether the form should be compound.
     *
     * @param bool $compound whether the form should be compound.
     *
     * @return $this the configuration object.
     *
     * @see FormConfigInterface::getCompound()
     */
    public function setCompound($compound);

    /**
     * Set the types.
     *
     * @param ResolvedFormTypeInterface $type the type of the form.
     *
     * @return $this the configuration object.
     */
    public function setType(ResolvedFormTypeInterface $type);

    /**
     * Sets the initial data of the form.
     *
     * @param mixed $data the data of the form in application format.
     *
     * @return $this the configuration object.
     */
    public function setData($data);

    /**
     * Locks the form's data to the data passed in the configuration.
     *
     * A form with locked data is restricted to the data passed in
     * this configuration. The data can only be modified then by
     * submitting the form.
     *
     * @param bool $locked whether to lock the default data.
     *
     * @return $this the configuration object.
     */
    public function setDataLocked($locked);

    /**
     * Sets the form factory used for creating new forms.
     *
     * @param FormFactoryInterface $formFactory the form factory.
     */
    public function setFormFactory(FormFactoryInterface $formFactory);

    /**
     * Sets the target URL of the form.
     *
     * @param string $action the target URL of the form.
     *
     * @return $this the configuration object.
     */
    public function setAction($action);

    /**
     * Sets the HTTP method used by the form.
     *
     * @param string $method the HTTP method of the form.
     *
     * @return $this the configuration object.
     */
    public function setMethod($method);

    /**
     * Sets the request handler used by the form.
     *
     * @param RequestHandlerInterface $requestHandler
     *
     * @return $this the configuration object.
     */
    public function setRequestHandler(RequestHandlerInterface $requestHandler);

    /**
     * Sets whether the form should be initialized automatically.
     *
     * Should be set to true only for root forms.
     *
     * @param bool $initialize true to initialize the form automatically,
     *                         false to suppress automatic initialization.
     *                         In the second case, you need to call
     *                         {@link FormInterface::initialize()} manually.
     *
     * @return $this the configuration object.
     */
    public function setAutoInitialize($initialize);

    /**
     * Builds and returns the form configuration.
     *
     * @return FormConfigInterface
     */
    public function getFormConfig();
}
