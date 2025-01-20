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
 * A choice loader that loads its choices and values lazily, only when necessary.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class LazyChoiceLoader implements ChoiceLoaderInterface
{
    private ?ChoiceListInterface $choiceList = null;

    public function __construct(
        private readonly ChoiceLoaderInterface $loader,
    ) {
    }

    public function loadChoiceList(?callable $value = null): ChoiceListInterface
    {
        return $this->choiceList ??= new ArrayChoiceList([], $value);
    }

    public function loadChoicesForValues(array $values, ?callable $value = null): array
    {
        $choices = $this->loader->loadChoicesForValues($values, $value);
        $this->choiceList = new ArrayChoiceList($choices, $value);

        return $choices;
    }

    public function loadValuesForChoices(array $choices, ?callable $value = null): array
    {
        $values = $this->loader->loadValuesForChoices($choices, $value);

        if ($this->choiceList?->getValuesForChoices($choices) !== $values) {
            $this->loadChoicesForValues($values, $value);
        }

        return $values;
    }
}
