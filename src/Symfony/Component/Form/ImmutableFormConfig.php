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

/**
 * An immutable form configuration.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ImmutableFormConfig implements FormConfigInterface
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
     * Creates an immutable copy of a given configuration.
     *
     * @param  FormConfigInterface $config The configuration to copy.
     */
    public function __construct(FormConfigInterface $config)
    {
        $this->dispatcher = $config->getEventDispatcher();
        $this->name = $config->getName();
        $this->types = $config->getTypes();
        $this->clientTransformers = $config->getClientTransformers();
        $this->normTransformers = $config->getNormTransformers();
        $this->dataMapper = $config->getDataMapper();
        $this->validators = $config->getValidators();
        $this->required = $config->getRequired();
        $this->disabled = $config->getDisabled();
        $this->errorBubbling = $config->getErrorBubbling();
        $this->emptyData = $config->getEmptyData();
        $this->data = $config->getData();
        $this->attributes = $config->getAttributes();
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
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientTransformers()
    {
        return $this->clientTransformers;
    }

    /**
     * {@inheritdoc}
     */
    public function getNormTransformers()
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
    function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * {@inheritdoc}
     */
    function getAttribute($name)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        return $this->data;
    }
}
