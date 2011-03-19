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
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FormBuilder extends FieldBuilder
{
    private $dataClass;

    private $csrfFieldName;

    private $csrfProvider;

    private $fields = array();

    private $dataMapper;

    public function __construct(ThemeInterface $theme,
            EventDispatcherInterface $dispatcher,
            CsrfProviderInterface $csrfProvider)
    {
        parent::__construct($theme, $dispatcher);

        $this->csrfProvider = $csrfProvider;
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

        // TODO turn order of $type and $name around

        if (func_num_args() > 2 || (func_num_args() > 1 && !is_array(func_get_arg(1)))) {
            $type = func_get_arg(0);
            $name = func_get_arg(1);
            $options = func_num_args() > 2 ? func_get_arg(2) : array();

            $this->fields[$name] = array(
                'type' => $type,
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

        if (isset($this->fields[$name]['type'])) {
            $this->fields[$name] = $this->getFormFactory()->createBuilder(
                $this->fields[$name]['type'],
                $name,
                $this->fields[$name]['options']
            );

            return $this->fields[$name];
        }

        $this->fields[$name] = $this->getFormFactory()->createBuilderForProperty(
            $this->fields[$name]['class'],
            $name,
            $this->fields[$name]['options']
        );

        return $this->fields[$name];
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

    protected function buildFields()
    {
        $fields = array();

        foreach ($this->fields as $name => $field) {
            $fields[$name] = $this->get($name)->getInstance();
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
            $token = $this->csrfProvider->generateCsrfToken(get_class($this));

            $this->add('hidden', $this->csrfFieldName, array(
                'data' => $token,
                'property_path' => null,
            ));
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

    public function getInstance()
    {
        $this->buildCsrfProtection();

        $instance = new Form(
            $this->getName(),
            $this->buildDispatcher(),
            $this->buildRenderer(),
            $this->getClientTransformer(),
            $this->getNormTransformer(),
            $this->getDataMapper(),
            $this->getDataValidator(),
            $this->getRequired(),
            $this->getDisabled(),
            $this->getAttributes()
        );

        foreach ($this->buildFields() as $field) {
            $instance->add($field);
        }

        if ($this->getData()) {
            $instance->setData($this->getData());
        }

        return $instance;
    }
}