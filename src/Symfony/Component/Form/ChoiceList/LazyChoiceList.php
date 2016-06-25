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

use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

/**
 * A choice list that loads its choices lazily.
 *
 * The choices are fetched using a {@link ChoiceLoaderInterface} instance.
 * If only {@link getChoicesForValues()} or {@link getValuesForChoices()} is
 * called, the choice list is only loaded partially for improved performance.
 *
 * Once {@link getChoices()} or {@link getValues()} is called, the list is
 * loaded fully.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LazyChoiceList implements ChoiceListInterface
{
    /**
     * The choice loader.
     *
     * @var ChoiceLoaderInterface
     */
    private $loader;

    /**
     * The callable creating string values for each choice.
     *
     * If null, choices are simply cast to strings.
     *
     * @var null|callable
     */
    private $value;

    /**
     * @var ChoiceListInterface|null
     */
    private $loadedList;

    /**
     * Creates a lazily-loaded list using the given loader.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the choice as first and the array key as the second
     * argument.
     *
     * @param ChoiceLoaderInterface $loader The choice loader
     * @param null|callable         $value  The callable generating the choice
     *                                      values
     */
    public function __construct(ChoiceLoaderInterface $loader, $value = null)
    {
        $this->loader = $loader;
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getChoices()
    {
        if (!$this->loadedList) {
            $this->loadedList = $this->loader->loadChoiceList($this->value);
        }

        return $this->loadedList->getChoices();
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        if (!$this->loadedList) {
            $this->loadedList = $this->loader->loadChoiceList($this->value);
        }

        return $this->loadedList->getValues();
    }

    /**
     * {@inheritdoc}
     */
    public function getStructuredValues()
    {
        if (!$this->loadedList) {
            $this->loadedList = $this->loader->loadChoiceList($this->value);
        }

        return $this->loadedList->getStructuredValues();
    }

    /**
     * {@inheritdoc}
     */
    public function getOriginalKeys()
    {
        if (!$this->loadedList) {
            $this->loadedList = $this->loader->loadChoiceList($this->value);
        }

        return $this->loadedList->getOriginalKeys();
    }

    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $values)
    {
        if (!$this->loadedList) {
            return $this->loader->loadChoicesForValues($values, $this->value);
        }

        return $this->loadedList->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices)
    {
        if (!$this->loadedList) {
            return $this->loader->loadValuesForChoices($choices, $this->value);
        }

        return $this->loadedList->getValuesForChoices($choices);
    }
}
