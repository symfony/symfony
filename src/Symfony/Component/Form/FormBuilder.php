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

use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\DataMapper\DataMapperInterface;
use Symfony\Component\Form\DataTransformer\DataTransformerInterface;
use Symfony\Component\Form\Renderer\ThemeRenderer;
use Symfony\Component\Form\Renderer\FormRendererInterface;
use Symfony\Component\Form\Renderer\Plugin\FormRendererPluginInterface;
use Symfony\Component\Form\Validator\FormValidatorInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
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

    private $renderer;

    private $rendererVars = array();

    private $clientTransformer;

    private $normalizationTransformer;

    private $validators = array();

    private $attributes = array();

    private $parent;

    private $dataClass;

    private $csrfFieldName;

    private $csrfProvider;

    private $fields = array();

    private $dataMapper;

    private $errorBubbling = false;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
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

    public function setName($name)
    {
        $this->name = $name;

        return $this;
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

    protected function buildDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Sets the DataTransformer.
     *
     * @param DataTransformerInterface $clientTransformer
     */
    public function setNormTransformer(DataTransformerInterface $normalizationTransformer = null)
    {
        $this->normalizationTransformer = $normalizationTransformer;

        return $this;
    }

    public function getNormTransformer()
    {
        return $this->normalizationTransformer;
    }

    /**
     * Sets the DataTransformer.
     *
     * @param DataTransformerInterface $clientTransformer
     */
    public function setClientTransformer(DataTransformerInterface $clientTransformer = null)
    {
        $this->clientTransformer = $clientTransformer;

        return $this;
    }

    public function getClientTransformer()
    {
        return $this->clientTransformer;
    }

    /**
     * Sets the renderer
     *
     * @param FormRendererInterface $renderer
     */
    public function setRenderer(FormRendererInterface $renderer)
    {
        $this->renderer = $renderer;

        return $this;
    }

    public function addRendererPlugin(FormRendererPluginInterface $plugin)
    {
        $this->rendererVars[] = $plugin;

        return $this;
    }

    public function setRendererVar($name, $value)
    {
        $this->rendererVars[$name] = $value;

        return $this;
    }

    protected function buildRenderer()
    {
        foreach ($this->rendererVars as $name => $value) {
            if (!$this->renderer) {
                throw new FormException('A renderer must be set in order to add renderer variables or plugins');
            }

            if ($value instanceof FormRendererPluginInterface) {
                $this->renderer->addPlugin($value);
                continue;
            }

            $this->renderer->setVar($name, $value);
        }

        return $this->renderer;
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
    }

    public function getDataMapper()
    {
        return $this->dataMapper;
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
     * @param FormInterface|string $field
     * @return FormInterface
     */
    public function add($name, $type = null, array $options = array())
    {
        if (!is_string($name)) {
            throw new UnexpectedTypeException($name, 'string');
        }

        if (null !== $type && !is_string($type)) {
            throw new UnexpectedTypeException($type, 'string');
        }

        $this->fields[$name] = array(
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
                throw new FormException('The data class must be set to automatically create fields');
            }

            $builder = $this->getFormFactory()->createBuilderForProperty(
                $this->dataClass,
                $name,
                $options
            );
        }

        $this->fields[$name] = $builder;

        $builder->setParent($this);

        return $builder;
    }

    public function get($name)
    {
        if (!isset($this->fields[$name])) {
            throw new FormException(sprintf('The field "%s" does not exist', $name));
        }

        $field = $this->fields[$name];

        if ($field instanceof FormBuilder) {
            return $field;
        }

        return $this->build($name, $field['type'], $field['options']);
    }

    /**
     * Removes the field with the given name.
     *
     * @param string $name
     */
    public function remove($name)
    {
        if (isset($this->fields[$name])) {
            // field might still be lazy
            if ($this->fields[$name] instanceof FormInterface) {
                $this->fields[$name]->setParent(null);
            }

            unset($this->fields[$name]);
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
        return isset($this->fields[$name]);
    }

    protected function buildChildren()
    {
        $fields = array();

        foreach ($this->fields as $name => $builder) {
            if (!$builder instanceof FormBuilder) {
                $builder = $this->build($name, $builder['type'], $builder['options']);
            }

            $fields[$name] = $builder->getForm();
        }

        return $fields;
    }

    public function addCsrfProtection(CsrfProviderInterface $provider = null, $fieldName = '_token')
    {
        if (null !== $provider) {
            $this->csrfProvider = $provider;
        }

        $this->csrfFieldName = $fieldName;
    }

    public function removeCsrfProtection()
    {
        $this->csrfFieldName = null;

        return $this;
    }

    /**
     * @return true if this form is CSRF protected
     */
    public function hasCsrfProtection()
    {
        return isset($this->csrfFieldName);
    }

    public function getCsrfFieldName()
    {
        return $this->csrfFieldName;
    }

    public function getCsrfProvider()
    {
        return $this->csrfProvider;
    }

    protected function buildCsrfProtection()
    {
        if ($this->hasCsrfProtection()) {
            // need a page ID here, maybe FormType class?
            $options = array('page_id' => null);

            if ($this->csrfProvider) {
                $options['csrf_provider'] = $this->csrfProvider;
            }

            $this->add($this->csrfFieldName, 'csrf', $options);
        }
    }

    public function setDataClass($class)
    {
        $this->dataClass = $class;

        return $this;
    }

    public function getDataClass()
    {
        return $this->dataClass;
    }

    public function getForm()
    {
        $this->buildCsrfProtection();

        $instance = new Form(
            $this->getName(),
            $this->buildDispatcher(),
            $this->buildRenderer(),
            $this->getClientTransformer(),
            $this->getNormTransformer(),
            $this->getDataMapper(),
            $this->getValidators(),
            $this->getRequired(),
            $this->getReadOnly(),
            $this->getErrorBubbling(),
            $this->getAttributes()
        );

        foreach ($this->buildChildren() as $field) {
            $instance->add($field);
        }

        if ($this->getData()) {
            $instance->setData($this->getData());
        }

        return $instance;
    }
}