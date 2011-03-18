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
use Symfony\Component\Form\DataValidator\DataValidatorInterface;
use Symfony\Component\Form\DataTransformer\DataTransformerInterface;
use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\Renderer\RendererInterface;
use Symfony\Component\Form\Renderer\Plugin\RendererPluginInterface;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FieldBuilder
{
    private $name;

    private $data;

    private $dataClass;

    private $dispatcher;

    private $factory;

    private $csrfFieldName;

    private $csrfProvider;

    private $disabled;

    private $required;

    private $renderer;

    private $rendererVars = array();

    private $dataTransformer;

    private $normalizationTransformer;

    private $theme;

    private $fields = array();

    private $dataMapper;

    private $dataValidator;

    private $attributes = array();

    public function __construct(ThemeInterface $theme,
            EventDispatcherInterface $dispatcher,
            CsrfProviderInterface $csrfProvider)
    {
        $this->theme = $theme;
        $this->dispatcher = $dispatcher;
        $this->csrfProvider = $csrfProvider;
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

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setDisabled($disabled)
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function getDisabled()
    {
        return $this->disabled;
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

    public function setDataMapper(DataMapperInterface $dataMapper)
    {
        $this->dataMapper = $dataMapper;
    }

    public function getDataMapper()
    {
        return $this->dataMapper;
    }

    public function setDataValidator(DataValidatorInterface $dataValidator)
    {
        $this->dataValidator = $dataValidator;
    }

    public function getDataValidator()
    {
        return $this->dataValidator;
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
     * @param FieldInterface|string $field
     * @return FieldInterface
     */
    public function add($field)
    {
        // if the field is given as string, ask the field factory of the form
        // to create a field
        if ($field instanceof FieldInterface) {
            $this->fields[$field->getName()] = $field;

            return $this;
        }

        if (!is_string($field)) {
            throw new UnexpectedTypeException($field, 'FieldInterface or string');
        }

        // TODO turn order of $identifier and $name around

        if (func_num_args() > 2 || (func_num_args() > 1 && !is_array(func_get_arg(1)))) {
            $identifier = func_get_arg(0);
            $name = func_get_arg(1);
            $options = func_num_args() > 2 ? func_get_arg(2) : array();

            $this->fields[$name] = array(
                'identifier' => $identifier,
                'options' => $options,
            );

            return $this;
        }

        if (!$this->dataClass) {
            throw new FormException('The data class must be set to automatically create fields');
        }

        $property = func_get_arg(0);
        $options = func_num_args() > 1 ? func_get_arg(1) : array();

        $this->fields[$property] = array(
            'class' => $this->dataClass,
            'options' => $options,
        );

        return $this;
    }

    public function get($name)
    {
        if (!isset($this->fields[$name])) {
            throw new FormException(sprintf('The field "%s" does not exist', $name));
        }

        if ($this->fields[$name] instanceof FieldBuilder) {
            return $this->fields[$name];
        }

        if (isset($this->fields[$name]['identifier'])) {
            $this->fields[$name] = $this->factory->createBuilder(
                $this->fields[$name]['identifier'],
                $name,
                $this->fields[$name]['options']
            );

            return $this->fields[$name];
        }

        $this->fields[$name] = $this->factory->createBuilderForProperty(
            $this->fields[$name]['class'],
            $name,
            $this->fields[$name]['options']
        );

        return $this->fields[$name];
    }

    protected function buildFields()
    {
        $fields = array();

        foreach ($this->fields as $name => $field) {
            $fields[$name] = $this->get($name)->getInstance();
        }

        return $fields;
    }

    /**
     * Removes the field with the given name.
     *
     * @param string $name
     */
    public function remove($name)
    {
        $this->fields[$name]->setParent(null);

        unset($this->fields[$name]);
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
            $token = $this->csrfProvider->generateCsrfToken(get_class($this));

            $this->add('hidden', $this->csrfFieldName, array(
                'data' => $token,
                'property_path' => null,
            ));
        }
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
     * @param DataTransformerInterface $dataTransformer
     */
    public function setNormalizationTransformer(DataTransformerInterface $normalizationTransformer = null)
    {
        $this->normalizationTransformer = $normalizationTransformer;

        return $this;
    }

    public function getNormalizationTransformer()
    {
        return $this->normalizationTransformer;
    }

    /**
     * Sets the DataTransformer.
     *
     * @param DataTransformerInterface $dataTransformer
     */
    public function setDataTransformer(DataTransformerInterface $dataTransformer = null)
    {
        $this->dataTransformer = $dataTransformer;

        return $this;
    }

    public function getDataTransformer()
    {
        return $this->dataTransformer;
    }

    /**
     * Sets the renderer
     *
     * @param RendererInterface $renderer
     */
    public function setRenderer(RendererInterface $renderer)
    {
        $this->renderer = $renderer;

        return $this;
    }

    public function addRendererPlugin(RendererPluginInterface $plugin)
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
        if (!$this->renderer) {
            $this->renderer = new DefaultRenderer($this->theme, 'text');
        }

        foreach ($this->rendererVars as $name => $value) {
            if ($value instanceof RendererPluginInterface) {
                $this->renderer->addPlugin($value);
                continue;
            }

            $this->renderer->setVar($name, $value);
        }

        return $this->renderer;
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

    public function getInstance()
    {
        $this->buildCsrfProtection();

        if (count($this->fields) > 0) {
            $instance = new Form(
                $this->name,
                $this->buildDispatcher(),
                $this->buildRenderer(),
                $this->getDataTransformer(),
                $this->getNormalizationTransformer(),
                $this->getDataMapper(),
                $this->getDataValidator(),
                $this->getRequired(),
                $this->getDisabled(),
                $this->getAttributes()
            );

            foreach ($this->buildFields() as $field) {
                $instance->add($field);
            }
        } else {
            $instance = new Field(
                $this->name,
                $this->buildDispatcher(),
                $this->buildRenderer(),
                $this->getDataTransformer(),
                $this->getNormalizationTransformer(),
                $this->getDataValidator(),
                $this->getRequired(),
                $this->getDisabled(),
                $this->getAttributes()
            );
        }

        if ($this->data) {
            $instance->setData($this->data);
        }

        return $instance;
    }
}