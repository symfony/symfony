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

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * A field group that repeats the given field multiple times over a collection
 * specified by the property path if the field.
 *
 * Example usage:
 *
 *     $form->add(new CollectionField(new TextField('emails')));
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class CollectionField extends Form
{
    /**
     * Remembers which fields were removed upon submitting
     * @var array
     */
    protected $removedFields = array();

    /**
     * The prototype field for the collection rows
     * @var FieldInterface
     */
    protected $prototype;

    public function __construct($key, array $options = array())
    {
        // This doesn't work with addOption(), because the value of this option
        // needs to be accessed before Configurable::__construct() is reached
        // Setting all options in the constructor of the root field
        // is conceptually flawed
        if (isset($options['prototype'])) {
            $this->prototype = $options['prototype'];
            unset($options['prototype']);
        }

        parent::__construct($key, $options);
    }

    /**
     * Available options:
     *
     *  * modifiable:   If true, elements in the collection can be added
     *                  and removed by the presence of absence of the
     *                  corresponding field groups. Field groups could be
     *                  added or removed via Javascript and reflected in
     *                  the underlying collection. Default: false.
     */
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

    public function submit($data)
    {
        $this->removedFields = array();

        if (null === $data) {
            $data = array();
        }

        foreach ($this as $name => $field) {
            if (!isset($data[$name]) && $this->getOption('modifiable') && '$$key$$' != $name) {
                $this->remove($name);
                $this->removedFields[] = $name;
            }
        }

        foreach ($data as $name => $value) {
            if (!isset($this[$name]) && $this->getOption('modifiable')) {
                $this->add($this->newField($name, $name));
            }
        }

        parent::submit($data);
    }

    protected function writeObject(&$objectOrArray)
    {
        parent::writeObject($objectOrArray);

        foreach ($this->removedFields as $name) {
            unset($objectOrArray[$name]);
        }
    }

    protected function newField($key, $propertyPath)
    {
        if (null !== $propertyPath) {
            $propertyPath = '['.$propertyPath.']';
        }

        if ($this->prototype) {
            $field = clone $this->prototype;
            $field->setKey($key);
            $field->setPropertyPath($propertyPath);
        } else {
            $field = new TextField($key, array(
                'property_path' => $propertyPath,
            ));
        }

        return $field;
    }
}