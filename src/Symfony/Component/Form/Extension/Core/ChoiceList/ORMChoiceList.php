<?php

/*
 * This file is part of the Symfony package.
*
* (c) Fabien Potencier <fabien@symfony.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Symfony\Component\Form\Extension\Core\ChoiceList;

/**
 * Abstract class for ORM implementations of ChoiceListInterface
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class ORMChoiceList extends ObjectChoiceList
{
    /**
     * @var string
     */
    protected $class;

    /**
     * Whether the model objects have already been loaded.
     *
     * @var Boolean
     */
    protected $loaded = false;

    /**
     * {@inheritdoc}
     */
    public function getChoices()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return parent::getChoices();
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return parent::getValues();
    }

    /**
     * {@inheritdoc}
     */
    public function getPreferredViews()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return parent::getPreferredViews();
    }

    /**
     * {@inheritdoc}
     */
    public function getRemainingViews()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return parent::getRemainingViews();
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $objects)
    {
        if (!$this->loaded) {
            // Optimize performance for single-field identifiers. We already
            // know that the IDs are used as values

            // Attention: This optimization does not check choices for existence
            if ($this->optimizedIdentifierCheck()) {
                $values = array();

                foreach ($objects as $object) {
                    if ($object instanceof $this->class) {
                        // Make sure to convert to the right format
                        $values[] = $this->fixValue(current($this->getIdentifierValues($object)));
                    }
                }

                return $values;
            }

            $this->load();
        }

        return parent::getValuesForChoices($objects);
    }

    /**
     * {@inheritdoc}
     */
    public function getIndicesForChoices(array $objects)
    {
        if (!$this->loaded) {
            // Optimize performance for single-field identifiers. We already
            // know that the IDs are used as indices

            // Attention: This optimization does not check choices for existence
            if ($this->optimizedIdentifierCheck()) {
                $indices = array();

                foreach ($objects as $object) {
                    if ($object instanceof $this->class) {
                        // Make sure to convert to the right format
                        $indices[] = $this->fixIndex(current($this->getIdentifierValues($object)));
                    }
                }

                return $indices;
            }

            $this->load();
        }

        return parent::getIndicesForChoices($objects);
    }

    /**
     * Creates a new unique value for this ORM object.
     *
     * If the ORM object has a single-field identifier, this identifier is used.
     *
     * Otherwise a new integer is generated.
     *
     * @param mixed $object The choice to create a value for
     *
     * @return integer|string A unique value without character limitations.
     */
    protected function createValue($object)
    {
        if ($this->optimizedIdentifierCheck()) {
            return (string) current($this->getIdentifierValues($object));
        }

        return parent::createValue($object);
    }

    /**
     * This optimization does not check choices for existence
     *
     * Should be implemented by child classes.
     *
     * @return Boolean
     */
    abstract protected function optimizedIdentifierCheck();

    /**
     * Loads the list with ORM objects.
     */
    abstract protected function load();

    /**
     * Returns the values of the identifier fields of an object.
     *
     * The ORM must know about this object, that is, the object must already
     * be persisted or added to the identity map before. Otherwise an
     * exception is thrown.
     *
     * @param object $object The object for which to get the identifier
     *
     * @return array          The identifier values
     *
     * @throws Exception If the object does not exist in ORM's identity map
     */
    abstract protected function getIdentifierValues($object);
}

