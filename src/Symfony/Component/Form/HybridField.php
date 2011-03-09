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

use Symfony\Component\Form\Exception\FormException;

/**
 * A field that can dynamically act like a field or like a field group
 *
 * You can use the method setFieldMode() to switch between the modes
 * HybridField::FIELD and HybridField::FORM. This is useful when you want
 * to create a field that, depending on its configuration, can either be
 * a single field or a combination of different fields (e.g. a date field
 * that might be a textbox or several select boxes).
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class HybridField extends Form
{
    const FIELD = 0;
    const FORM = 1;

    protected $mode = self::FIELD;

    /**
     * Sets the current mode of the field
     *
     * Note that you can't switch modes anymore once you have added children to
     * this field.
     *
     * @param integer $mode  One of the constants HybridField::FIELD and
     *                       HybridField::FORM.
     */
    public function setFieldMode($mode)
    {
        if (count($this) > 0 && $mode === self::FIELD) {
            throw new FormException('Switching to mode FIELD is not allowed after adding nested fields');
        }

        $this->mode = $mode;
    }

    /**
     * @return Boolean
     */
    public function isField()
    {
        return self::FIELD === $this->mode;
    }

    /**
     * @return Boolean
     */
    public function isGroup()
    {
        return self::FORM === $this->mode;
    }

    /**
     * @return integer
     */
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
    public function add($field)
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
        if ($this->mode === self::FORM) {
            return parent::getDisplayedData();
        }

        return Field::getDisplayedData();
    }

    /**
     * {@inheritDoc}
     */
    public function setData($data)
    {
        if ($this->mode === self::FORM) {
            parent::setData($data);
        } else {
            Field::setData($data);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function submit($data)
    {
        if ($this->mode === self::FORM) {
            parent::submit($data);
        } else {
            Field::submit($data);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty()
    {
        if ($this->mode === self::FORM) {
            return parent::isEmpty();
        }

        return Field::isEmpty();
    }
}