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

use Symfony\Component\Form\DataMapper\DataMapperInterface;
use Symfony\Component\Form\DataTransformer\DataTransformerInterface;
use Symfony\Component\Form\Validator\FormValidatorInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Type\FormTypeInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormBuilder
{
    private $name;

    private $data;

    private $dispatcher;

    private $factory;

    private $readOnly;

    private $required;

    private $clientTransformers = array();

    private $normTransformers = array();

    private $validators = array();

    private $attributes = array();

    private $types = array();

    private $parent;

    private $dataClass;

    private $children = array();

    private $dataMapper;

    private $errorBubbling = false;

    private $emptyData = '';

    public function __construct($name, EventDispatcherInterface $dispatcher, $dataClass = null)
    {
        $this->name = $name;
        $this->dispatcher = $dispatcher;
        $this->dataClass = $dataClass;
    }

    public function setFormFactory(FormFactoryInterface $factory)
    {
        $this->factory = $factory;

        return $this;
    }

    public function getFormFactory()
    {
        return $this->factory;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setParent(FormBuilder $builder)
    {
        $this->parent = $builder;

        return $this;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function end()
    {
        return $this->parent;
    }

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setReadOnly($readOnly)
    {
        $this->readOnly = $readOnly;

        return $this;
    }

    public function getReadOnly()
    {
        return $this->readOnly;
    }

    /**
     * Sets whether this field is required to be filled out when bound.
     *
     * @param Boolean $required
     */
    public function setRequired($required)
    {
        $this->required = $required;

        return $this;
    }

    public function getRequired()
    {
        return $this->required;
    }

    public function setErrorBubbling($errorBubbling)
    {
        $this->errorBubbling = $errorBubbling;

        return $this;
    }

    public function getErrorBubbling()
    {
        return $this->errorBubbling;
    }

    public function addValidator(FormValidatorInterface $validator)
    {
        $this->validators[] = $validator;

        return $this;
    }

    public function getValidators()
    {
        return $this->validators;
    }

    /**
     * Adds an event listener for events on this field
     *
     * @see Symfony\Component\EventDispatcher\EventDispatcherInterface::addEventListener
     */
    public function addEventListener($eventNames, $listener, $priority = 0)
    {
        $this->dispatcher->addListener($eventNames, $listener, $priority);

        return $this;
    }

    /**
     * Adds an event subscriber for events on this field
     *
     * @see Symfony\Component\EventDispatcher\EventDispatcherInterface::addEventSubscriber
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber, $priority = 0)
    {
        $this->dispatcher->addSubscriber($subscriber, $priority);

        return $this;
    }

    /**
     * Appends a transformer to the normalization transformer chain
     *
     * @param DataTransformerInterface $clientTransformer
     */
    public function appendNormTransformer(DataTransformerInterface $normTransformer = null)
    {
        $this->normTransformers[] = $normTransformer;

        return $this;
    }

    /**
     * Prepends a transformer to the client transformer chain
     *
     * @param DataTransformerInterface $clientTransformer
     */
    public function prependNormTransformer(DataTransformerInterface $normTransformer = null)
    {
        array_unshift($this->normTransformers, $normTransformer);

        return $this;
    }

    public function resetNormTransformers()
    {
        $this->normTransformers = array();

        return $this;
    }

    public function getNormTransformers()
    {
        return $this->normTransformers;
    }

    /**
     * Appends a transformer to the client transformer chain
     *
     * @param DataTransformerInterface $clientTransformer
     */
    public function appendClientTransformer(DataTransformerInterface $clientTransformer = null)
    {
        $this->clientTransformers[] = $clientTransformer;

        return $this;
    }

    /**
     * Prepends a transformer to the client transformer chain
     *
     * @param DataTransformerInterface $clientTransformer
     */
    public function prependClientTransformer(DataTransformerInterface $clientTransformer = null)
    {
        array_unshift($this->clientTransformers, $clientTransformer);

        return $this;
    }

    public function resetClientTransformers()
    {
        $this->clientTransformers = array();
    }

    public function getClientTransformers()
    {
        return $this->clientTransformers;
    }

    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    public function getAttribute($name)
    {
        return $this->attributes[$name];
    }

    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function setDataMapper(DataMapperInterface $dataMapper)
    {
        $this->dataMapper = $dataMapper;

        return $this;
    }

    public function getDataMapper()
    {
        return $this->dataMapper;
    }

    public function setTypes(array $types)
    {
        $this->types = $types;

        return $this;
    }

    public function getTypes()
    {
        return $this->types;
    }

    public function setEmptyData($emptyData)
    {
        $this->emptyData = $emptyData;

        return $this;
    }

    public function getEmptyData()
    {
        return $this->emptyData;
    }

    /**
     * Adds a new field to this group. A field must have a unique name within
     * the group. Otherwise the existing field is overwritten.
     *
     * If you add a nested group, this group should also be represented in the
     * object hierarchy. If you want to add a group that operates on the same
     * hierarchy level, use merge().
     *
     * <code>
     * class Entity
     * {
     *   public $location;
     * }
     *
     * class Location
     * {
     *   public $longitude;
     *   public $latitude;
     * }
     *
     * $entity = new Entity();
     * $entity->location = new Location();
     *
     * $form = new Form('entity', $entity, $validator);
     *
     * $locationGroup = new Form('location');
     * $locationGroup->add(new TextField('longitude'));
     * $locationGroup->add(new TextField('latitude'));
     *
     * $form->add($locationGroup);
     * </code>
     *
     * @param FormInterface|string $form
     * @return FormInterface
     */
    public function add($name, $type = null, array $options = array())
    {
        if (!is_string($name)) {
            throw new UnexpectedTypeException($name, 'string');
        }

        if (null !== $type && !is_string($type) && !$type instanceof FormTypeInterface) {
            throw new UnexpectedTypeException($type, 'string or Symfony\Component\Form\Type\FormTypeInterface');
        }

        $this->children[$name] = array(
            'type' => $type,
            'options' => $options,
        );

        return $this;
    }

    public function build($name, $type = null, array $options = array())
    {
        if (null !== $type) {
            $builder = $this->getFormFactory()->createBuilder(
                $type,
                $name,
                $options
            );
        } else {
            if (!$this->dataClass) {
                throw new FormException('The data class must be set to automatically create children');
            }

            $builder = $this->getFormFactory()->createBuilderForProperty(
                $this->dataClass,
                $name,
                $options
            );
        }

        $this->children[$name] = $builder;

        $builder->setParent($this);

        return $builder;
    }

    public function get($name)
    {
        if (!isset($this->children[$name])) {
            throw new FormException(sprintf('The field "%s" does not exist', $name));
        }

        $child = $this->children[$name];

        if ($child instanceof FormBuilder) {
            return $child;
        }

        return $this->build($name, $child['type'], $child['options']);
    }

    /**
     * Removes the field with the given name.
     *
     * @param string $name
     */
    public function remove($name)
    {
        if (isset($this->children[$name])) {
            // field might still be lazy
            if ($this->children[$name] instanceof FormInterface) {
                $this->children[$name]->setParent(null);
            }

            unset($this->children[$name]);
        }
    }

    /**
     * Returns whether a field with the given name exists.
     *
     * @param  string $name
     * @return Boolean
     */
    public function has($name)
    {
        return isset($this->children[$name]);
    }

    protected function buildDispatcher()
    {
        return $this->dispatcher;
    }

    protected function buildChildren()
    {
        $children = array();

        foreach ($this->children as $name => $builder) {
            if (!$builder instanceof FormBuilder) {
                $builder = $this->build($name, $builder['type'], $builder['options']);
            }

            $children[$builder->getName()] = $builder->getForm();
        }

        return $children;
    }

    public function getForm()
    {
        $instance = new Form(
            $this->getName(),
            $this->buildDispatcher(),
            $this->getTypes(),
            $this->getClientTransformers(),
            $this->getNormTransformers(),
            $this->getDataMapper(),
            $this->getValidators(),
            $this->getRequired(),
            $this->getReadOnly(),
            $this->getErrorBubbling(),
            $this->getEmptyData(),
            $this->getAttributes()
        );

        foreach ($this->buildChildren() as $child) {
            $instance->add($child);
        }

        if ($this->getData()) {
            $instance->setData($this->getData());
        }

        return $instance;
    }
}