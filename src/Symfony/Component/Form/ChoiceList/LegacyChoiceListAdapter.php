<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList;

use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface as LegacyChoiceListInterface;

/**
 * Adapts a legacy choice list implementation to {@link ChoiceListInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Added for backwards compatibility in Symfony 2.7, to be
 *             removed in Symfony 3.0.
 */
class LegacyChoiceListAdapter implements ChoiceListInterface
{
    /**
     * @var LegacyChoiceListInterface
     */
    private $adaptedList;

    /**
     * @var array|null
     */
    private $choices;

    /**
     * @var array|null
     */
    private $values;

    /**
     * @var array|null
     */
    private $structuredValues;

    /**
     * Adapts a legacy choice list to {@link ChoiceListInterface}.
     *
     * @param LegacyChoiceListInterface $adaptedList The adapted list
     */
    public function __construct(LegacyChoiceListInterface $adaptedList)
    {
        $this->adaptedList = $adaptedList;
    }

    /**
     * {@inheritdoc}
     */
    public function getChoices()
    {
        if (!$this->choices) {
            $this->initialize();
        }

        return $this->choices;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        if (!$this->values) {
            $this->initialize();
        }

        return $this->values;
    }

    /**
     * {@inheritdoc}
     */
    public function getStructuredValues()
    {
        if (!$this->structuredValues) {
            $this->initialize();
        }

        return $this->structuredValues;
    }

    /**
     * {@inheritdoc}
     */
    public function getOriginalKeys()
    {
        if (!$this->structuredValues) {
            $this->initialize();
        }

        return array_flip($this->structuredValues);
    }

    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $values)
    {
        return $this->adaptedList->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices)
    {
        return $this->adaptedList->getValuesForChoices($choices);
    }

    /**
     * Returns the adapted choice list.
     *
     * @return LegacyChoiceListInterface The adapted list
     */
    public function getAdaptedList()
    {
        return $this->adaptedList;
    }

    private function initialize()
    {
        $this->choices = array();
        $this->values = array();
        $this->structuredValues = $this->adaptedList->getValues();

        foreach ($this->adaptedList->getChoices() as $index => $choice) {
            $value = $this->structuredValues[$index];
            $this->values[] = $value;
            $this->choices[$value] = $choice;
        }
    }
}
