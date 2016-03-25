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
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;

/**
 * Creates {@link ChoiceListInterface} instances.
 *
 * Provides a BC layer for 3.x.
 *
 * To be deprecated in 3.4 and removed in 4.0, in favor of {@link ChoiceListFactoryInterface::createview()}
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Jules Pietri <jules@heahprod.com>
 */
interface ExpandedChoiceListFactoryInterface extends ChoiceListFactoryInterface
{
    /**
     * Creates a view for the given choice list.
     *
     * Callables may be passed for all optional arguments. The callables receive
     * the choice as first and the array key as the second argument.
     *
     *  * The callable for the label and the name should return the generated
     *    label/choice name.
     *  * The callable for the preferred choices should return true or false,
     *    depending on whether the choice should be preferred or not.
     *  * The callable for the grouping should return the group name or null if
     *    a choice should not be grouped.
     *  * The callable for the attributes should return an array of HTML
     *    attributes that will be inserted in the tag of the choice.
     *  * The callable for the label attributes should return an array of HTML
     *    attributes that will be inserted in the tag of the choice.
     *
     * If no callable is passed, the labels will be generated from the choice
     * keys. The view indices will be generated using an incrementing integer
     * by default.
     *
     * The preferred choices can also be passed as array. Each choice that is
     * contained in that array will be marked as preferred.
     *
     * The attributes can be passed as multi-dimensional array. The keys should
     * match the keys of the choices. The values should be arrays of HTML
     * attributes that should be added to the respective choice.
     *
     * The label attributes can be passed as an array. It will be used for
     * each choice.
     *
     * @param ChoiceListInterface $list             The choice list
     * @param null|array|callable $preferredChoices The preferred choices
     * @param null|callable       $label            The callable generating the
     *                                              choice labels
     * @param null|callable       $index            The callable generating the
     *                                              view indices
     * @param null|callable       $groupBy          The callable generating the
     *                                              group names
     * @param null|array|callable $attr             The callable generating the
     *                                              HTML attributes
     * @param null|array|callable $labelAttr        The callable generating the
     *                                              HTML attributes
     *
     * @return ChoiceListView The choice list view
     */
    public function createExpandedView(ChoiceListInterface $list, $preferredChoices = null, $label = null, $index = null, $groupBy = null, $attr = null, $labelAttr = null);
}
