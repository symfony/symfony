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
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
abstract class AbstractChoiceLoader implements ChoiceLoaderInterface
{
    private ?iterable $choices;

    /**
     * @final
     */
    public function loadChoiceList(callable $value = null): ChoiceListInterface
    {
        return new ArrayChoiceList($this->choices ??= $this->loadChoices(), $value);
    }

    public function loadChoicesForValues(array $values, callable $value = null): array
    {
        if (!$values) {
            return [];
        }

        return $this->doLoadChoicesForValues($values, $value);
    }

    public function loadValuesForChoices(array $choices, callable $value = null): array
    {
        if (!$choices) {
            return [];
        }

        if ($value) {
            // if a value callback exists, use it
            return array_map(fn ($item) => (string) $value($item), $choices);
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
