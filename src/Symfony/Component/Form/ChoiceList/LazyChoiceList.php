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
     * If null, choices are cast to strings.
     */
    private ?\Closure $value;

    /**
     * Creates a lazily-loaded list using the given loader.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the choice as first and the array key as the second
     * argument.
     *
     * @param callable|null $value The callable generating the choice values
     */
    public function __construct(ChoiceLoaderInterface $loader, callable $value = null)
    {
        $this->loader = $loader;
        $this->value = null === $value || $value instanceof \Closure ? $value : \Closure::fromCallable($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getChoices(): array
    {
        return $this->loader->loadChoiceList($this->value)->getChoices();
    }

    /**
     * {@inheritdoc}
     */
    public function getValues(): array
    {
        return $this->loader->loadChoiceList($this->value)->getValues();
    }

    /**
     * {@inheritdoc}
     */
    public function getStructuredValues(): array
    {
        return $this->loader->loadChoiceList($this->value)->getStructuredValues();
    }

    /**
     * {@inheritdoc}
     */
    public function getOriginalKeys(): array
    {
        return $this->loader->loadChoiceList($this->value)->getOriginalKeys();
    }

    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $values): array
    {
        return $this->loader->loadChoicesForValues($values, $this->value);
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices): array
    {
        return $this->loader->loadValuesForChoices($choices, $this->value);
    }
}
