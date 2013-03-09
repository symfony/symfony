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

use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\Exception;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\PropertyAccess\PropertyPathInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\ImmutableEventDispatcher;

/**
 * A basic form configuration.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormConfigBuilder implements FormConfigBuilderInterface
{
    /**
     * @var Boolean
     */
    protected $locked = false;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var string
     */
    private $name;

    /**
     * @var PropertyPathInterface
     */
    private $propertyPath;

    /**
     * @var Boolean
     */
    private $mapped = true;

    /**
     * @var Boolean
     */
    private $byReference = true;

    /**
     * @var Boolean
     */
    private $virtual = false;

    /**
     * @var Boolean
     */
    private $compound = false;

    /**
     * @var ResolvedFormTypeInterface
     */
    private $type;

    /**
     * @var array
     */
    private $viewTransformers = array();

    /**
     * @var array
     */
    private $modelTransformers = array();

    /**
     * @var DataMapperInterface
     */
    private $dataMapper;

    /**
     * @var array
     */
    private $validators = array();

    /**
     * @var Boolean
     */
    private $required = true;

    /**
     * @var Boolean
     */
    private $disabled = false;

    /**
     * @var Boolean
     */
    private $errorBubbling = false;

    /**
     * @var mixed
     */
    private $emptyData;

    /**
     * @var array
     */
    private $attributes = array();

    /**
     * @var mixed
     */
    private $data;

    /**
     * @var string
     */
    private $dataClass;

    /**
     * @var Boolean
     */
    private $dataLocked;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var array
     */
    private $options;

    /**
     * Creates an empty form configuration.
     *
     * @param string|integer           $name       The form name
     * @param string                   $dataClass  The class of the form's data
     * @param EventDispatcherInterface $dispatcher The event dispatcher
     * @param array                    $options    The form options
     *
     * @throws \InvalidArgumentException If the data class is not a valid class or if
     *                                   the name contains invalid characters.
     */
    public function __construct($name, $dataClass, EventDispatcherInterface $dispatcher, array $options = array())
    {
        self::validateName($name);

        if (null !== $dataClass && !class_exists($dataClass)) {
            throw new \InvalidArgumentException(sprintf('The data class "%s" is not a valid class.', $dataClass));
        }

        $this->name = (string) $name;
        $this->dataClass = $dataClass;
        $this->dispatcher = $dispatcher;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function addEventListener($eventName, $listener, $priority = 0)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dispatcher->addListener($eventName, $listener, $priority);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dispatcher->addSubscriber($subscriber);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addValidator(FormValidatorInterface $validator)
    {
        trigger_error('addValidator() is deprecated since version 2.1 and will be removed in 2.3.', E_USER_DEPRECATED);

        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->validators[] = $validator;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addViewTransformer(DataTransformerInterface $viewTransformer, $forcePrepend = false)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        if ($forcePrepend) {
            array_unshift($this->viewTransformers, $viewTransformer);
        } else {
            $this->viewTransformers[] = $viewTransformer;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resetViewTransformers()
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->viewTransformers = array();

        return $this;
    }

    /**
     * Alias of {@link addViewTransformer()}.
     *
     * @param DataTransformerInterface $viewTransformer
     *
     * @return FormConfigBuilder The configuration object.
     *
     * @throws BadMethodCallException if the form configuration is locked
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link addViewTransformer()} instead.
     */
    public function appendClientTransformer(DataTransformerInterface $viewTransformer)
    {
        trigger_error('appendClientTransformer() is deprecated since version 2.1 and will be removed in 2.3. Use addViewTransformer() instead.', E_USER_DEPRECATED);

        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return $this->addViewTransformer($viewTransformer);
    }

    /**
     * Prepends a transformer to the client transformer chain.
     *
     * @param DataTransformerInterface $viewTransformer
     *
     * @return FormConfigBuilder The configuration object.
     *
     * @throws BadMethodCallException if the form configuration is locked
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function prependClientTransformer(DataTransformerInterface $viewTransformer)
    {
        trigger_error('prependClientTransformer() is deprecated since version 2.1 and will be removed in 2.3.', E_USER_DEPRECATED);

        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return $this->addViewTransformer($viewTransformer, true);
    }

    /**
     * Alias of {@link resetViewTransformers()}.
     *
     * @return FormConfigBuilder The configuration object.
     *
     * @throws BadMethodCallException if the form configuration is locked
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link resetViewTransformers()} instead.
     */
    public function resetClientTransformers()
    {
        trigger_error('resetClientTransformers() is deprecated since version 2.1 and will be removed in 2.3. Use resetViewTransformers() instead.', E_USER_DEPRECATED);

        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return $this->resetViewTransformers();
    }

    /**
     * {@inheritdoc}
     */
    public function addModelTransformer(DataTransformerInterface $modelTransformer, $forceAppend = false)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        if ($forceAppend) {
            $this->modelTransformers[] = $modelTransformer;
        } else {
            array_unshift($this->modelTransformers, $modelTransformer);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resetModelTransformers()
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->modelTransformers = array();

        return $this;
    }

    /**
     * Appends a transformer to the normalization transformer chain
     *
     * @param DataTransformerInterface $modelTransformer
     *
     * @return FormConfigBuilder The configuration object.
     *
     * @throws BadMethodCallException if the form configuration is locked
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function appendNormTransformer(DataTransformerInterface $modelTransformer)
    {
        trigger_error('appendNormTransformer() is deprecated since version 2.1 and will be removed in 2.3.', E_USER_DEPRECATED);

        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return $this->addModelTransformer($modelTransformer, true);
    }

    /**
     * Alias of {@link addModelTransformer()}.
     *
     * @param DataTransformerInterface $modelTransformer
     *
     * @return FormConfigBuilder The configuration object.
     *
     * @throws BadMethodCallException if the form configuration is locked
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link addModelTransformer()} instead.
     */
    public function prependNormTransformer(DataTransformerInterface $modelTransformer)
    {
        trigger_error('prependNormTransformer() is deprecated since version 2.1 and will be removed in 2.3. Use addModelTransformer() instead.', E_USER_DEPRECATED);

        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return $this->addModelTransformer($modelTransformer);
    }

    /**
     * Alias of {@link resetModelTransformers()}.
     *
     * @return FormConfigBuilder The configuration object.
     *
     * @throws BadMethodCallException if the form configuration is locked
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link resetModelTransformers()} instead.
     */
    public function resetNormTransformers()
    {
        trigger_error('resetNormTransformers() is deprecated since version 2.1 and will be removed in 2.3. Use resetModelTransformers() instead.', E_USER_DEPRECATED);

        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        return $this->resetModelTransformers();
    }

    /**
     * {@inheritdoc}
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyPath()
    {
        return $this->propertyPath;
    }

    /**
     * {@inheritdoc}
     */
    public function getMapped()
    {
        return $this->mapped;
    }

    /**
     * {@inheritdoc}
     */
    public function getByReference()
    {
        return $this->byReference;
    }

    /**
     * {@inheritdoc}
     */
    public function getVirtual()
    {
        return $this->virtual;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompound()
    {
        return $this->compound;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getViewTransformers()
    {
        return $this->viewTransformers;
    }

    /**
     * Alias of {@link getViewTransformers()}.
     *
     * @return array The view transformers.
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link getViewTransformers()} instead.
     */
    public function getClientTransformers()
    {
        trigger_error('getClientTransformers() is deprecated since version 2.1 and will be removed in 2.3. Use getViewTransformers() instead.', E_USER_DEPRECATED);

        return $this->getViewTransformers();
    }

    /**
     * {@inheritdoc}
     */
    public function getModelTransformers()
    {
        return $this->modelTransformers;
    }

    /**
     * Alias of {@link getModelTransformers()}.
     *
     * @return array The model transformers.
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3. Use
     *             {@link getModelTransformers()} instead.
     */
    public function getNormTransformers()
    {
        trigger_error('getNormTransformers() is deprecated since version 2.1 and will be removed in 2.3. Use getModelTransformers() instead.', E_USER_DEPRECATED);

        return $this->getModelTransformers();
    }

    /**
     * {@inheritdoc}
     */
    public function getDataMapper()
    {
        return $this->dataMapper;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidators()
    {
        trigger_error('getValidators() is deprecated since version 2.1 and will be removed in 2.3.', E_USER_DEPRECATED);

        return $this->validators;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequired()
    {
        return $this->required;
    }

    /**
     * {@inheritdoc}
     */
    public function getDisabled()
    {
        return $this->disabled;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorBubbling()
    {
        return $this->errorBubbling;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmptyData()
    {
        return $this->emptyData;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($name, $default = null)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataClass()
    {
        return $this->dataClass;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataLocked()
    {
        return $this->dataLocked;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormFactory()
    {
        return $this->formFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($name, $value)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttributes(array $attributes)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->attributes = $attributes;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDataMapper(DataMapperInterface $dataMapper = null)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dataMapper = $dataMapper;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDisabled($disabled)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->disabled = (Boolean) $disabled;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setEmptyData($emptyData)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->emptyData = $emptyData;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setErrorBubbling($errorBubbling)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->errorBubbling = null === $errorBubbling ? null : (Boolean) $errorBubbling;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequired($required)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->required = (Boolean) $required;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPropertyPath($propertyPath)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        if (null !== $propertyPath && !$propertyPath instanceof PropertyPathInterface) {
            $propertyPath = new PropertyPath($propertyPath);
        }

        $this->propertyPath = $propertyPath;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMapped($mapped)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->mapped = $mapped;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setByReference($byReference)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->byReference = $byReference;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setVirtual($virtual)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->virtual = $virtual;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCompound($compound)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->compound = $compound;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setType(ResolvedFormTypeInterface $type)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->type = $type;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setData($data)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->data = $data;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDataLocked($locked)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->dataLocked = $locked;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormFactory(FormFactoryInterface $formFactory)
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        $this->formFactory = $formFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormConfig()
    {
        if ($this->locked) {
            throw new BadMethodCallException('FormConfigBuilder methods cannot be accessed anymore once the builder is turned into a FormConfigInterface instance.');
        }

        // This method should be idempotent, so clone the builder
        $config = clone $this;
        $config->locked = true;

        if (!$config->dispatcher instanceof ImmutableEventDispatcher) {
            $config->dispatcher = new ImmutableEventDispatcher($config->dispatcher);
        }

        return $config;
    }

    /**
     * Validates whether the given variable is a valid form name.
     *
     * @param string|integer $name The tested form name.
     *
     * @throws UnexpectedTypeException   If the name is not a string or an integer.
     * @throws \InvalidArgumentException If the name contains invalid characters.
     */
    public static function validateName($name)
    {
        if (null !== $name && !is_string($name) && !is_int($name)) {
            throw new UnexpectedTypeException($name, 'string, integer or null');
        }

        if (!self::isValidName($name)) {
            throw new \InvalidArgumentException(sprintf(
                'The name "%s" contains illegal characters. Names should start with a letter, digit or underscore and only contain letters, digits, numbers, underscores ("_"), hyphens ("-") and colons (":").',
                $name
            ));
        }
    }

    /**
     * Returns whether the given variable contains a valid form name.
     *
     * A name is accepted if it
     *
     *   * is empty
     *   * starts with a letter, digit or underscore
     *   * contains only letters, digits, numbers, underscores ("_"),
     *     hyphens ("-") and colons (":")
     *
     * @param string $name The tested form name.
     *
     * @return Boolean Whether the name is valid.
     */
    public static function isValidName($name)
    {
        return '' === $name || null === $name || preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_\-:]*$/D', $name);
    }
}
