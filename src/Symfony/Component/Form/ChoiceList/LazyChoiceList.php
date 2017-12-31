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
     *
     * @deprecated Since 3.1, to be removed in 4.0. Cache the choice list in the {@link ChoiceLoaderInterface} instead.
     */
    private $loadedList;

    /**
     * @var bool
     *
     * @deprecated Flag used for BC layer since 3.1. To be removed in 4.0.
     */
    private $loaded = false;

    /**
     * Creates a lazily-loaded list using the given loader.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the choice as first and the array key as the second
     * argument.
     *
     * @param ChoiceLoaderInterface $loader The choice loader
     * @param null|callable         $value  The callable generating the choice values
     */
    public function __construct(ChoiceLoaderInterface $loader, callable $value = null)
    {
        $this->loader = $loader;
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getChoices()
    {
        if ($this->loaded) {
            // We can safely invoke the {@link ChoiceLoaderInterface} assuming it has the list
            // in cache when the lazy list is already loaded
            if ($this->loadedList !== $this->loader->loadChoiceList($this->value)) {
                @trigger_error(sprintf('Caching the choice list in %s is deprecated since Symfony 3.1 and will not happen in 4.0. Cache the list in the %s instead.', __CLASS__, ChoiceLoaderInterface::class), E_USER_DEPRECATED);
            }

            return $this->loadedList->getChoices();
        }

        // BC
        $this->loadedList = $this->loader->loadChoiceList($this->value);
        $this->loaded = true;

        return $this->loadedList->getChoices();
        // In 4.0 keep the following line only:
        // return $this->loader->loadChoiceList($this->value)->getChoices()
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        if ($this->loaded) {
            // Check whether the loader has the same cache
            if ($this->loadedList !== $this->loader->loadChoiceList($this->value)) {
                @trigger_error(sprintf('Caching the choice list in %s is deprecated since Symfony 3.1 and will not happen in 4.0. Cache the list in the %s instead.', __CLASS__, ChoiceLoaderInterface::class), E_USER_DEPRECATED);
            }

            return $this->loadedList->getValues();
        }

        // BC
        $this->loadedList = $this->loader->loadChoiceList($this->value);
        $this->loaded = true;

        return $this->loadedList->getValues();
        // In 4.0 keep the following line only:
        // return $this->loader->loadChoiceList($this->value)->getValues()
    }

    /**
     * {@inheritdoc}
     */
    public function getStructuredValues()
    {
        if ($this->loaded) {
            // Check whether the loader has the same cache
            if ($this->loadedList !== $this->loader->loadChoiceList($this->value)) {
                @trigger_error(sprintf('Caching the choice list in %s is deprecated since Symfony 3.1 and will not happen in 4.0. Cache the list in the %s instead.', __CLASS__, ChoiceLoaderInterface::class), E_USER_DEPRECATED);
            }

            return $this->loadedList->getStructuredValues();
        }

        // BC
        $this->loadedList = $this->loader->loadChoiceList($this->value);
        $this->loaded = true;

        return $this->loadedList->getStructuredValues();
        // In 4.0 keep the following line only:
        // return $this->loader->loadChoiceList($this->value)->getStructuredValues();
    }

    /**
     * {@inheritdoc}
     */
    public function getOriginalKeys()
    {
        if ($this->loaded) {
            // Check whether the loader has the same cache
            if ($this->loadedList !== $this->loader->loadChoiceList($this->value)) {
                @trigger_error(sprintf('Caching the choice list in %s is deprecated since Symfony 3.1 and will not happen in 4.0. Cache the list in the %s instead.', __CLASS__, ChoiceLoaderInterface::class), E_USER_DEPRECATED);
            }

            return $this->loadedList->getOriginalKeys();
        }

        // BC
        $this->loadedList = $this->loader->loadChoiceList($this->value);
        $this->loaded = true;

        return $this->loadedList->getOriginalKeys();
        // In 4.0 keep the following line only:
        // return $this->loader->loadChoiceList($this->value)->getOriginalKeys();
    }

    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $values)
    {
        if ($this->loaded) {
            // Check whether the loader has the same cache
            if ($this->loadedList !== $this->loader->loadChoiceList($this->value)) {
                @trigger_error(sprintf('Caching the choice list in %s is deprecated since Symfony 3.1 and will not happen in 4.0. Cache the list in the %s instead.', __CLASS__, ChoiceLoaderInterface::class), E_USER_DEPRECATED);
            }

            return $this->loadedList->getChoicesForValues($values);
        }

        return $this->loader->loadChoicesForValues($values, $this->value);
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices)
    {
        if ($this->loaded) {
            // Check whether the loader has the same cache
            if ($this->loadedList !== $this->loader->loadChoiceList($this->value)) {
                @trigger_error(sprintf('Caching the choice list in %s is deprecated since Symfony 3.1 and will not happen in 4.0. Cache the list in the %s instead.', __CLASS__, ChoiceLoaderInterface::class), E_USER_DEPRECATED);
            }

            return $this->loadedList->getValuesForChoices($choices);
        }

        return $this->loader->loadValuesForChoices($choices, $this->value);
    }
}
