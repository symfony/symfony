<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Form\Exception\FormException;

/**
 * A field that can dynamically act like a field or like a field group
 *
 * You can use the method setFieldMode() to switch between the modes
 * HybridField::FIELD and HybridField::GROUP. This is useful when you want
 * to create a field that, depending on its configuration, can either be
 * a single field or a combination of different fields.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class HybridField extends FieldGroup
{
    const FIELD = 0;
    const GROUP = 1;

    protected $mode = self::FIELD;

    /**
     * Sets the current mode of the field
     *
     * Note that you can't switch modes anymore once you have added children to
     * this field.
     *
     * @param integer $mode  One of the constants HybridField::FIELD and
     *                       HybridField::GROUP.
     */
    public function setFieldMode($mode)
    {
        if (count($this) > 0 && $mode === self::FIELD) {
            throw new FormException('Switching to mode FIELD is not allowed after adding nested fields');
        }

        $this->mode = $mode;
    }

    public function isField()
    {
        return self::FIELD === $this->mode;
    }

    public function isGroup()
    {
        return self::GROUP === $this->mode;
    }

    public function getFieldMode()
    {
        return $this->mode;
    }

    /**
     * {@inheritDoc}
     *
     * @throws FormException  When the field is in mode HybridField::FIELD adding
     *                        subfields is not allowed
     */
    public function add(FieldInterface $field)
    {
        if ($this->mode === self::FIELD) {
            throw new FormException('You cannot add nested fields while in mode FIELD');
        }

        return parent::add($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getDisplayedData()
    {
        if ($this->mode === self::GROUP) {
            return parent::getDisplayedData();
        } else {
            return Field::getDisplayedData();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setData($data)
    {
        if ($this->mode === self::GROUP) {
            parent::setData($data);
        } else {
            Field::setData($data);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function bind($data)
    {
        if ($this->mode === self::GROUP) {
            parent::bind($data);
        } else {
            Field::bind($data);
        }
    }
}