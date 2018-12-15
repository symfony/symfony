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
 * Loads an {@link ArrayChoiceList} instance from a callable returning an array of choices.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class CallbackChoiceLoader implements ChoiceLoaderInterface
{
    private $callback;

    /**
     * The loaded choice list.
     *
     * @var ArrayChoiceList
     */
    private $choiceList;

    /**
     * @param callable $callback The callable returning an array of choices
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        if (null !== $this->choiceList) {
            return $this->choiceList;
        }

        return $this->choiceList = new ArrayChoiceList(\call_user_func($this->callback), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        // Optimize
        if (empty($values)) {
            return [];
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        // Optimize
        if (empty($choices)) {
            return [];
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
