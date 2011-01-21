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

/**
 * A field for repeated input of values
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class RepeatedField extends FieldGroup
{
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

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('first_key', 'first');
        $this->addOption('second_key', 'second');

        parent::configure();

        $field = clone $this->prototype;
        $field->setKey($this->getOption('first_key'));
        $field->setPropertyPath($this->getOption('first_key'));
        $this->add($field);

        $field = clone $this->prototype;
        $field->setKey($this->getOption('second_key'));
        $field->setPropertyPath($this->getOption('second_key'));
        $this->add($field);
    }

    /**
     * Returns whether both entered values are equal
     *
     * @return Boolean
     */
    public function isFirstEqualToSecond()
    {
        return $this->get($this->getOption('first_key'))->getData() === $this->get($this->getOption('second_key'))->getData();
    }

    /**
     * Sets the values of both fields to this value
     *
     * @param mixed $data
     */
    public function setData($data)
    {
        parent::setData(array(
            $this->getOption('first_key') => $data,
            $this->getOption('second_key') => $data
        ));
    }

    /**
     * Return only value of first password field.
     *
     * @return string The password.
     */
    public function getData()
    {
        if ($this->isBound() && $this->isFirstEqualToSecond()) {
            return $this->get($this->getOption('first_key'))->getData();
        }

        return null;
    }
}
