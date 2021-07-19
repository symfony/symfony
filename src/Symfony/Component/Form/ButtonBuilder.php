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
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * A builder for {@link Button} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonBuilder implements \IteratorAggregate, FormBuilderInterface
{
    protected $locked = false;

    /**
     * @var bool
     */
    private $disabled = false;

    /**
     * @var ResolvedFormTypeInterface
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $options;

    /**
     * @throws InvalidArgumentException if the name is empty
     */
    public function __construct(?string $name, array $options = [])
    {
        if ('' === $name || null === $name) {
            throw new InvalidArgumentException('Buttons cannot have empty names.');
        }

        $this->name = $name;
        $this->options = $options;

        FormConfigBuilder::validateName($name);
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function add($child, string $type = null, array $options = [])
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function create(string $name, string $type = null, array $options = [])
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function get(string $name)
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function remove(string $name)
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function has(string $name)
    {
        return false;
    }

    /**
     * Returns the children.
     *
     * @return array Always returns an empty array
     */
    public function all()
    {
        return [];
    }

    /**
     * Creates the button.
     *
     * @return Button The button
     */
    public function getForm()
    {
        return new Button($this->getFormConfig());
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function addEventListener(string $eventName, callable $listener, int $priority = 0)
    {
        throw new BadMethodCallException('Buttons do not support event listeners.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber)
    {
        throw new BadMethodCallException('Buttons do not support event subscribers.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function addViewTransformer(DataTransformerInterface $viewTransformer, bool $forcePrepend = false)
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function resetViewTransformers()
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function addModelTransformer(DataTransformerInterface $modelTransformer, bool $forceAppend = false)
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function resetModelTransformers()
    {
        throw new BadMethodCallException('Buttons do not support data transformers.');
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute(string $name, $value)
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setDataMapper(DataMapperInterface $dataMapper = null)
    {
        throw new BadMethodCallException('Buttons do not support data mappers.');
    }

    /**
     * Set whether the button is disabled.
     *
     * @return $this
     */
    public function setDisabled(bool $disabled)
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setEmptyData($emptyData)
    {
        throw new BadMethodCallException('Buttons do not support empty data.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setErrorBubbling(bool $errorBubbling)
    {
        throw new BadMethodCallException('Buttons do not support error bubbling.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setRequired(bool $required)
    {
        throw new BadMethodCallException('Buttons cannot be required.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setPropertyPath($propertyPath)
    {
        throw new BadMethodCallException('Buttons do not support property paths.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setMapped(bool $mapped)
    {
        throw new BadMethodCallException('Buttons do not support data mapping.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setByReference(bool $byReference)
    {
        throw new BadMethodCallException('Buttons do not support data mapping.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setCompound(bool $compound)
    {
        throw new BadMethodCallException('Buttons cannot be compound.');
    }

    /**
     * Sets the type of the button.
     *
     * @return $this
     */
    public function setType(ResolvedFormTypeInterface $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setData($data)
    {
        throw new BadMethodCallException('Buttons do not support data.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setDataLocked(bool $locked)
    {
        throw new BadMethodCallException('Buttons do not support data locking.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setFormFactory(FormFactoryInterface $formFactory)
    {
        throw new BadMethodCallException('Buttons do not support form factories.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setAction(string $action)
    {
        throw new BadMethodCallException('Buttons do not support actions.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setMethod(string $method)
    {
        throw new BadMethodCallException('Buttons do not support methods.');
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setRequestHandler(RequestHandlerInterface $requestHandler)
    {
        throw new BadMethodCallException('Buttons do not support request handlers.');
    }

    /**
     * Unsupported method.
     *
     * @return $this
     *
     * @throws BadMethodCallException
     */
    public function setAutoInitialize(bool $initialize)
    {
        if (true === $initialize) {
            throw new BadMethodCallException('Buttons do not support automatic initialization.');
        }

        return $this;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setInheritData(bool $inheritData)
    {
        throw new BadMethodCallException('Buttons do not support data inheritance.');
    }

    /**
     * Builds and returns the button configuration.
     *
     * @return FormConfigInterface
     */
    public function getFormConfig()
    {
        // This method should be idempotent, so clone the builder
        $config = clone $this;
        $config->locked = true;

        return $config;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function setIsEmptyCallback(?callable $isEmptyCallback)
    {
        throw new BadMethodCallException('Buttons do not support "is empty" callback.');
    }

    /**
     * Unsupported method.
     */
    public function getEventDispatcher()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Unsupported method.
     */
    public function getPropertyPath()
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getMapped()
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getByReference()
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getCompound()
    {
        return false;
    }

    /**
     * Returns the form type used to construct the button.
     *
     * @return ResolvedFormTypeInterface The button's type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Unsupported method.
     *
     * @return array Always returns an empty array
     */
    public function getViewTransformers()
    {
        return [];
    }

    /**
     * Unsupported method.
     *
     * @return array Always returns an empty array
     */
    public function getModelTransformers()
    {
        return [];
    }

    /**
     * Unsupported method.
     */
    public function getDataMapper()
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getRequired()
    {
        return false;
    }

    /**
     * Returns whether the button is disabled.
     *
     * @return bool Whether the button is disabled
     */
    public function getDisabled()
    {
        return $this->disabled;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getErrorBubbling()
    {
        return false;
    }

    /**
     * Unsupported method.
     */
    public function getEmptyData()
    {
        return null;
    }

    /**
     * Returns additional attributes of the button.
     *
     * @return array An array of key-value combinations
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Returns whether the attribute with the given name exists.
     *
     * @return bool Whether the attribute exists
     */
    public function hasAttribute(string $name)
    {
        return \array_key_exists($name, $this->attributes);
    }

    /**
     * Returns the value of the given attribute.
     *
     * @param mixed $default The value returned if the attribute does not exist
     *
     * @return mixed The attribute value
     */
    public function getAttribute(string $name, $default = null)
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * Unsupported method.
     */
    public function getData()
    {
        return null;
    }

    /**
     * Unsupported method.
     */
    public function getDataClass()
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getDataLocked()
    {
        return false;
    }

    /**
     * Unsupported method.
     */
    public function getFormFactory()
    {
        throw new BadMethodCallException('Buttons do not support adding children.');
    }

    /**
     * Unsupported method.
     */
    public function getAction()
    {
        return null;
    }

    /**
     * Unsupported method.
     */
    public function getMethod()
    {
        return null;
    }

    /**
     * Unsupported method.
     */
    public function getRequestHandler()
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getAutoInitialize()
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false
     */
    public function getInheritData()
    {
        return false;
    }

    /**
     * Returns all options passed during the construction of the button.
     *
     * @return array The passed options
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Returns whether a specific option exists.
     *
     * @return bool Whether the option exists
     */
    public function hasOption(string $name)
    {
        return \array_key_exists($name, $this->options);
    }

    /**
     * Returns the value of a specific option.
     *
     * @param mixed $default The value returned if the option does not exist
     *
     * @return mixed The option value
     */
    public function getOption(string $name, $default = null)
    {
        return \array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function getIsEmptyCallback(): ?callable
    {
        throw new BadMethodCallException('Buttons do not support "is empty" callback.');
    }

    /**
     * Unsupported method.
     *
     * @return int Always returns 0
     */
    public function count()
    {
        return 0;
    }

    /**
     * Unsupported method.
     *
     * @return \EmptyIterator Always returns an empty iterator
     */
    public function getIterator()
    {
        return new \EmptyIterator();
    }
}
