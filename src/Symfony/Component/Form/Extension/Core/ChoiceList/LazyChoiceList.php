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

use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * A choice list that is loaded lazily
 *
 * This list loads itself as soon as any of the getters is accessed for the
 * first time. You should implement loadChoiceList() in your child classes,
 * which should return a ChoiceListInterface instance.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @since v2.1.0
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
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getChoices()
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getChoices();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getValues()
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getValues();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getPreferredViews()
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getPreferredViews();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getRemainingViews()
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getRemainingViews();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getChoicesForValues(array $values)
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getValuesForChoices(array $choices)
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getValuesForChoices($choices);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getIndicesForChoices(array $choices)
    {
        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getIndicesForChoices($choices);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
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
     *
     * @since v2.1.0
     */
    abstract protected function loadChoiceList();

    /**
     * @since v2.1.0
     */
    private function load()
    {
        $choiceList = $this->loadChoiceList();

        if (!$choiceList instanceof ChoiceListInterface) {
            throw new InvalidArgumentException(sprintf('loadChoiceList() should return a ChoiceListInterface instance. Got %s', gettype($choiceList)));
        }

        $this->choiceList = $choiceList;
    }
}
