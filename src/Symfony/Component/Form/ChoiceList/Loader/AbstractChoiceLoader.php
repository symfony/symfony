<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Loader;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
abstract class AbstractChoiceLoader implements ChoiceLoaderInterface
{
    /**
     * The loaded choice list.
     *
     * @var ArrayChoiceList
     */
    private $choiceList;

    /**
     * @final
     *
     * {@inheritdoc}
     */
    public function loadChoiceList(callable $value = null)
    {
        return $this->choiceList ?? ($this->choiceList = new ArrayChoiceList($this->loadChoices(), $value));
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, callable $value = null)
    {
        if (!$values) {
            return [];
        }

        if ($this->choiceList) {
            return $this->choiceList->getChoicesForValues($values);
        }

        return $this->doLoadChoicesForValues($values, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, callable $value = null)
    {
        if (!$choices) {
            return [];
        }

        if ($value) {
            // if a value callback exists, use it
            return array_map($value, $choices);
        }

        if ($this->choiceList) {
            return $this->choiceList->getValuesForChoices($choices);
        }

        return $this->doLoadValuesForChoices($choices);
    }

    abstract protected function loadChoices(): iterable;

    protected function doLoadChoicesForValues(array $values, ?callable $value): array
    {
        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    protected function doLoadValuesForChoices(array $choices): array
    {
        return $this->loadChoiceList()->getValuesForChoices($choices);
    }
}
