<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class CollectionField extends FieldGroup
{
    /**
     * The prototype for the inner fields
     * @var FieldInterface
     */
    protected $prototype;

    /**
     * Remembers which fields were removed upon binding
     * @var array
     */
    protected $removedFields = array();

    /**
     * Repeats the given field twice to verify the user's input
     *
     * @param FieldInterface $innerField
     */
    public function __construct(FieldInterface $innerField, array $options = array())
    {
        $this->prototype = $innerField;

        parent::__construct($innerField->getKey(), $options);
    }

    protected function configure()
    {
        $this->addOption('modifiable', false);

        if ($this->getOption('modifiable')) {
            $field = $this->newField('$$key$$', null);
            // TESTME
            $field->setRequired(false);
            $this->add($field);
        }

        parent::configure();
    }

    public function setData($collection)
    {
        if (!is_array($collection) && !$collection instanceof \Traversable) {
            throw new UnexpectedTypeException($collection, 'array or \Traversable');
        }

        foreach ($this as $name => $field) {
            if (!$this->getOption('modifiable') || '$$key$$' != $name) {
                $this->remove($name);
            }
        }

        foreach ($collection as $name => $value) {
            $this->add($this->newField($name, $name));
        }

        parent::setData($collection);
    }

    public function bind($taintedData)
    {
        $this->removedFields = array();

        if (null === $taintedData) {
            $taintedData = array();
        }

        foreach ($this as $name => $field) {
            if (!isset($taintedData[$name]) && $this->getOption('modifiable') && '$$key$$' != $name) {
                $this->remove($name);
                $this->removedFields[] = $name;
            }
        }

        foreach ($taintedData as $name => $value) {
            if (!isset($this[$name]) && $this->getOption('modifiable')) {
                $this->add($this->newField($name, $name));
            }
        }

        parent::bind($taintedData);
    }

    protected function updateObject(&$objectOrArray)
    {
        parent::updateObject($objectOrArray);

        foreach ($this->removedFields as $name) {
            unset($objectOrArray[$name]);
        }
    }

    protected function newField($key, $propertyPath)
    {
        $field = clone $this->prototype;
        $field->setKey($key);
        $field->setPropertyPath(null === $propertyPath ? null : '['.$propertyPath.']');
        return $field;
    }
}