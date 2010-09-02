<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: FieldGroup.php 79 2009-12-08 12:53:15Z bernhard $
 */
class CollectionField extends FieldGroup
{
    CONST PLACEHOLDER_KEY = '$$key$$';

    /**
     * The prototype for the inner fields
     * @var FieldInterface
     */
    protected $prototype;

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
    }

    public function setData($collection)
    {
        if (!is_array($collection) && !$collection instanceof \Traversable) {
            throw new UnexpectedTypeException('The data must be an array');
        }

        foreach ($this as $name => $field)
        {
            $this->remove($name);
        }

        foreach ($collection as $name => $value)
        {
            $this->add($this->newField($name, $name));
        }

        parent::setData($collection);
    }

    public function bind($taintedData)
    {
        if (is_null($taintedData)) {
            $taintedData = array();
        }

        if ($this->getOption('modifiable'))
        {
            unset($taintedData[self::PLACEHOLDER_KEY]);
            parent::setData(null);

            foreach ($this as $name => $field)
            {
                if (!isset($taintedData[$name]))
                {
                    $this->remove($name);
                }
            }

            foreach ($taintedData as $name => $value)
            {
                if (!isset($this[$name]))
                {
                    $this->add($this->newField($name, $name));
                }
            }
        }

        return parent::bind($taintedData);
    }

    protected function newField($key, $propertyPath)
    {
        $field = clone $this->prototype;
        $field->setKey($key);
        $field->setPropertyPath($propertyPath === null ? null : '['.$propertyPath.']');
        return $field;
    }

    public function getPlaceholderField()
    {
        $field = $this->newField(self::PLACEHOLDER_KEY, null);
        $field->setParent($this);
        return $field;
    }
}