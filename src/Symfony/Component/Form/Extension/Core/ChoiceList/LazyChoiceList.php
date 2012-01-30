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

use Symfony\Component\Form\Exception\FormException;

/**
 * A choice list that is loaded lazily
 *
 * This list loads itself as soon as any of the getters is accessed for the
 * first time. You should implement loadChoiceList() in your child classes,
 * which should return a ChoiceListInterface instance.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class LazyChoiceList implements ChoiceListInterface
{
    /**
     * The loaded choice list
     *
     * @var ChoiceListInterface
     */
    private $choiceList;

    /**
    * Returns the list of choices
     *
    * @return array The choices with their indices as keys.
    */
    public function getChoices()
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getChoices();
    }

    /**
    * Returns the values for the choices
    *
    * @return array The values with the corresponding choice indices as keys.
    */
    public function getValues()
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getValues();
    }

    /**
    * Returns the choice views of the preferred choices as nested array with
    * the choice groups as top-level keys.
    *
    * Example:
    *
    * <source>
    * array(
    *     'Group 1' => array(
    *         10 => ChoiceView object,
    *         20 => ChoiceView object,
    *     ),
    *     'Group 2' => array(
    *         30 => ChoiceView object,
    *     ),
    * )
    * </source>
    *
    * @return array A nested array containing the views with the corresponding
    *               choice indices as keys on the lowest levels and the choice
    *               group names in the keys of the higher levels.
    */
    public function getPreferredViews()
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getPreferredViews();
    }

    /**
    * Returns the choice views of the choices that are not preferred as nested
    * array with the choice groups as top-level keys.
    *
    * Example:
    *
    * <source>
    * array(
    *     'Group 1' => array(
    *         10 => ChoiceView object,
    *         20 => ChoiceView object,
    *     ),
    *     'Group 2' => array(
    *         30 => ChoiceView object,
    *     ),
    * )
    * </source>
    *
    * @return array A nested array containing the views with the corresponding
    *               choice indices as keys on the lowest levels and the choice
    *               group names in the keys of the higher levels.
    *
    * @see getPreferredValues
    */
    public function getRemainingViews()
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getRemainingViews();
    }

    /**
    * Returns the choices corresponding to the given values.
    *
    * @param array $values An array of choice values. Not existing values in
    *                      this array are ignored.
    *
    * @return array An array of choices with ascending, 0-based numeric keys
    */
    public function getChoicesForValues(array $values)
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getChoicesForValues($values);
    }

    /**
    * Returns the values corresponding to the given choices.
    *
    * @param array $choices An array of choices. Not existing choices in this
    *                       array are ignored.
    *
    * @return array An array of choice values with ascending, 0-based numeric
    *               keys
    */
    public function getValuesForChoices(array $choices)
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getValuesForChoices($choices);
    }

    /**
    * Returns the indices corresponding to the given choices.
    *
    * @param array $choices An array of choices. Not existing choices in this
    *                       array are ignored.
    *
    * @return array An array of indices with ascending, 0-based numeric keys
    */
    public function getIndicesForChoices(array $choices)
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getIndicesForChoices($choices);
    }

    /**
    * Returns the indices corresponding to the given values.
    *
    * @param array $values An array of choice values. Not existing values in
    *                      this array are ignored.
    *
    * @return array An array of indices with ascending, 0-based numeric keys
    */
    public function getIndicesForValues(array $values)
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getIndicesForValues($values);
    }

    /**
     * Loads the choice list
     *
     * Should be implemented by child classes.
     *
     * @return ChoiceListInterface The loaded choice list
     */
    abstract protected function loadChoiceList();

    private function load()
    {
        $choiceList = $this->loadChoiceList();

        if (!$choiceList instanceof ChoiceListInterface) {
            throw new FormException('loadChoiceList() should return a ChoiceListInterface instance. Got ' . gettype($choiceList));
        }

        $this->choiceList = $choiceList;
    }
}