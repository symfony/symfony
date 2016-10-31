<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Factory;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

/**
 * Filter the choices before passing them to the decorated factory.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class FilteringFactoryDecorator implements FilteredChoiceListFactoryInterface
{
    /**
     * @var ChoiceListFactoryInterface
     */
    private $decoratedFactory;

    /**
     * @var array[]
     */
    private $choicesByValues = array();

    /**
     * Decorates the given factory.
     *
     * @param ChoiceListFactoryInterface $decoratedFactory The decorated factory
     */
    public function __construct(ChoiceListFactoryInterface $decoratedFactory)
    {
        $this->decoratedFactory = $decoratedFactory;
    }

    /**
     * Returns the decorated factory.
     *
     * @return ChoiceListFactoryInterface The decorated factory
     */
    public function getDecoratedFactory()
    {
        return $this->decoratedFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function createListFromChoices($choices, $value = null)
    {
        return $this->decoratedFactory->createListFromChoices($choices, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function createListFromLoader(ChoiceLoaderInterface $loader, $value = null)
    {
        return $this->decoratedFactory->createListFromLoader($loader, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function createFilteredListFromChoices($choices, $value = null, callable $filter)
    {
        // We need to apply the filter on a resolved choices array in case
        // the same choices are filtered many times. The original choice list
        // should be cached by the decorated factory
        $choiceList = $this->decoratedFactory->createListFromChoices($choices, $value);

        // The filtered choice list should be cached by the decorated factory
        // if the same filter is applied on the same choices by values

        return $this->decoratedFactory->createListFromChoices(self::filterChoices($choiceList->getChoices(), $filter));
    }

    /**
     * {@inheritdoc}
     */
    public function createFilteredListFromLoader(ChoiceLoaderInterface $loader, $value = null, callable $filter)
    {
        // Don't hash the filter since the original choices may have been loaded already
        // with a different filter if any.
        $hash = CachingFactoryDecorator::generateHash(array($loader, $value));

        if (!isset($this->choicesByValues[$hash])) {
            // We need to load the choice list before filtering the choices
            $choiceList = $this->decoratedFactory->createListFromLoader($loader, $value);

            // Cache the choices by values, in case they are filtered many times,
            // the original choice list should already have been cached by the
            // previous call.
            $this->choicesByValues[$hash] = $choiceList->getChoices();
        }

        // The filtered choice list should be cached by the decorated factory
        // if the same filter is applied on the same choices by values

        return $this->decoratedFactory->createListFromChoices(self::filterChoices($this->choicesByValues[$hash], $filter));
    }

    /**
     * {@inheritdoc}
     */
    public function createView(ChoiceListInterface $list, $preferredChoices = null, $label = null, $index = null, $groupBy = null, $attr = null)
    {
        $this->decoratedFactory->createView($list, $preferredChoices, $label, $index, $groupBy, $attr);
    }

    /**
     * Filters the choices.
     *
     * @param array    $choices The choices by values to filter
     * @param callable $filter  The filter
     *
     * @return array The filtered choices
     */
    static private function filterChoices($choices, callable $filter)
    {
        foreach ($choices as $value => $choice) {
            if (call_user_func($filter, $choice, $value)) {
                continue;
            }
            unset($choices[$value]);
        }

        return $choices;
    }
}
