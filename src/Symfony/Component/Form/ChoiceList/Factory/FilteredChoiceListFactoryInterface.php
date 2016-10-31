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
 * Creates filtered {@link ChoiceListInterface} instances.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
interface FilteredChoiceListFactoryInterface extends ChoiceListFactoryInterface
{
    /**
     * Creates a filtered choice list for the given choices.
     *
     * The choices should be passed in the values of the choices array.
     *
     * The filter callable gets passed each choice and its resolved value
     * and should return true to keep the choice and false or null otherwise.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the choice as only argument.
     *
     * @param array|\Traversable $choices The choices
     * @param null|callable      $value   The callable generating the choice
     *                                    values
     * @param callable           $filter  The filter
     *
     * @return ChoiceListInterface The filtered choice list
     */
    public function createFilteredListFromChoices($choices, $value = null, callable $filter);

    /**
     * Creates a filtered choice list that is loaded with the given loader.
     *
     * The filter callable gets passed each choice and its resolved value
     * and should return true to keep the choice and false or null otherwise.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the choice as only argument.
     *
     * @param ChoiceLoaderInterface $loader The choice loader
     * @param null|callable         $value  The callable generating the choice
     *                                      values
     * @param callable              $filter The filter
     *
     * @return ChoiceListInterface The filtered choice list
     */
    public function createFilteredListFromLoader(ChoiceLoaderInterface $loader, $value = null, callable $filter);
}
