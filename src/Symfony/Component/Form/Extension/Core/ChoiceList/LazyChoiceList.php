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
 * A choice list that is loaded lazily.
 *
 * This list loads itself as soon as any of the getters is accessed for the
 * first time. You should implement loadChoiceList() in your child classes,
 * which should return a ChoiceListInterface instance.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Form\ChoiceList\LazyChoiceList} instead.
 */
abstract class LazyChoiceList implements ChoiceListInterface
{
    /**
     * The loaded choice list.
     *
     * @var ChoiceListInterface
     */
    private $choiceList;

    public function __construct()
    {
        trigger_error('The '.__CLASS__.' class is deprecated since version 2.7 and will be removed in 3.0. Use Symfony\Component\Form\ChoiceList\LazyChoiceList instead.', E_USER_DEPRECATED);
    }

    /**
     * {@inheritdoc}
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
     * @deprecated since version 2.4, to be removed in 3.0.
     */
    public function getIndicesForChoices(array $choices)
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.4 and will be removed in 3.0.', E_USER_DEPRECATED);

        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getIndicesForChoices($choices);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since version 2.4, to be removed in 3.0.
     */
    public function getIndicesForValues(array $values)
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.4 and will be removed in 3.0.', E_USER_DEPRECATED);

        if (!$this->choiceList) {
            $this->load();
        }

        return $this->choiceList->getIndicesForValues($values);
    }

    /**
     * Loads the choice list.
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
            throw new InvalidArgumentException(sprintf('loadChoiceList() should return a ChoiceListInterface instance. Got %s', gettype($choiceList)));
        }

        $this->choiceList = $choiceList;
    }
}
