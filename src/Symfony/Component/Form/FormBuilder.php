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
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FormBuilder extends FieldBuilder
{
    private $dataClass;

    private $csrfFieldName;

    private $csrfProvider;

    private $fields = array();

    private $dataMapper;

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

        if ($field instanceof FieldBuilder) {
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
            if ($this->fields[$name] instanceof FieldInterface) {
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

    protected function buildFields()
    {
        $fields = array();

        foreach ($this->fields as $name => $builder) {
            if (!$builder instanceof FieldBuilder) {
                $builder = $this->build($name, $builder['type'], $builder['options']);
            }

            $fields[$name] = $builder->getInstance();
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
            $this->getValidators(),
            $this->getRequired(),
            $this->getReadOnly(),
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