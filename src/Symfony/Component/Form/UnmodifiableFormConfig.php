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

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\EventDispatcher\UnmodifiableEventDispatcher;

/**
 * A read-only form configuration.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UnmodifiableFormConfig implements FormConfigInterface
{
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var string
     */
    private $name;

    /**
     * @var PropertyPath
     */
    private $propertyPath;

    /**
     * @var Boolean
     */
    private $mapped;

    /**
     * @var Boolean
     */
    private $byReference;

    /**
     * @var Boolean
     */
    private $virtual;

    /**
     * @var array
     */
    private $types;

    /**
     * @var array
     */
    private $clientTransformers;

    /**
     * @var array
     */
    private $normTransformers;

    /**
     * @var DataMapperInterface
     */
    private $dataMapper;

    /**
     * @var FormValidatorInterface
     */
    private $validators;

    /**
     * @var Boolean
     */
    private $required;

    /**
     * @var Boolean
     */
    private $disabled;

    /**
     * @var Boolean
     */
    private $errorBubbling;

    /**
     * @var mixed
     */
    private $emptyData;

    /**
     * @var array
     */
    private $attributes;

    /**
     * @var mixed
     */
    private $data;

    /**
     * @var string
     */
    private $dataClass;

    /**
     * @var array
     */
    private $options;

    /**
     * Creates an unmodifiable copy of a given configuration.
     *
     * @param  FormConfigInterface $config The configuration to copy.
     */
    public function __construct(FormConfigInterface $config)
    {
        $dispatcher = $config->getEventDispatcher();
        if (!$dispatcher instanceof UnmodifiableEventDispatcher)  {
            $dispatcher = new UnmodifiableEventDispatcher($dispatcher);
        }

        $this->dispatcher = $dispatcher;
        $this->name = $config->getName();
        $this->propertyPath = $config->getPropertyPath();
        $this->mapped = $config->getMapped();
        $this->byReference = $config->getByReference();
        $this->virtual = $config->getVirtual();
        $this->types = $config->getTypes();
        $this->clientTransformers = $config->getViewTransformers();
        $this->normTransformers = $config->getModelTransformers();
        $this->dataMapper = $config->getDataMapper();
        $this->validators = $config->getValidators();
        $this->required = $config->getRequired();
        $this->disabled = $config->getDisabled();
        $this->errorBubbling = $config->getErrorBubbling();
        $this->emptyData = $config->getEmptyData();
        $this->attributes = $config->getAttributes();
        $this->data = $config->getData();
        $this->dataClass = $config->getDataClass();
        $this->options = $config->getOptions();
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
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * {@inheritdoc}
     */
    public function getViewTransformers()
    {
        return $this->clientTransformers;
    }

    /**
     * {@inheritdoc}
     */
    public function getModelTransformers()
    {
        return $this->normTransformers;
    }

    /**
     * Returns the data mapper of the form.
     *
     * @return DataMapperInterface The data mapper.
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
}
