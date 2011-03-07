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

/**
 * A field for repeated input of values.
 *
 * Available options:
 *
 *  * first_key:        The key to use for the first field.
 *  * second_key:       The key to use for the second field.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class RepeatedField extends Form
{
    /**
     * The prototype for the inner fields
     * @var FieldInterface
     */
    protected $prototype;

    /**
     * Repeats the given field twice to verify the user's input.
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
     * Return the value of a child field
     *
     * If the value of the first field is set, this value is returned.
     * Otherwise the value of the second field is returned. This way,
     * this field will never trigger a NotNull/NotBlank error if any of the
     * child fields was filled in.
     *
     * @return string The field value
     */
    public function getData()
    {
        // Return whichever data is set. This should not return NULL if any of
        // the fields is set, otherwise this might trigger a NotNull/NotBlank
        // error even though some value was set
        $data1 = $this->get($this->getOption('first_key'))->getData();
        $data2 = $this->get($this->getOption('second_key'))->getData();

        return $data1 ?: $data2;
    }
}
