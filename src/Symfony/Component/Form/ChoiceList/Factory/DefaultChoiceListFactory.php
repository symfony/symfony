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

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

/**
 * Default implementation of {@link ChoiceListFactoryInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DefaultChoiceListFactory implements ChoiceListFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createListFromChoices(iterable $choices, callable $value = null)
    {
        return new ArrayChoiceList($choices, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function createListFromLoader(ChoiceLoaderInterface $loader, callable $value = null)
    {
        return new LazyChoiceList($loader, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function createView(ChoiceListInterface $list, $preferredChoices = null, $label = null, callable $index = null, callable $groupBy = null, $attr = null)
    {
        $preferredViews = [];
        $preferredViewsOrder = [];
        $otherViews = [];
        $choices = $list->getChoices();
        $keys = $list->getOriginalKeys();

        if (!\is_callable($preferredChoices)) {
            if (empty($preferredChoices)) {
                $preferredChoices = null;
            } else {
                // make sure we have keys that reflect order
                $preferredChoices = array_values($preferredChoices);
                $preferredChoices = static function ($choice) use ($preferredChoices) {
                    return array_search($choice, $preferredChoices, true);
                };
            }
        }

        // The names are generated from an incrementing integer by default
        if (null === $index) {
            $index = 0;
        }

        // If $groupBy is a callable returning a string
        // choices are added to the group with the name returned by the callable.
        // If $groupBy is a callable returning an array
        // choices are added to the groups with names returned by the callable
        // If the callable returns null, the choice is not added to any group
        if (\is_callable($groupBy)) {
            foreach ($choices as $value => $choice) {
                self::addChoiceViewsGroupedByCallable(
                    $groupBy,
                    $choice,
                    $value,
                    $label,
                    $keys,
                    $index,
                    $attr,
                    $preferredChoices,
                    $preferredViews,
                    $preferredViewsOrder,
                    $otherViews
                );
            }

            // Remove empty group views that may have been created by
            // addChoiceViewsGroupedByCallable()
            foreach ($preferredViews as $key => $view) {
                if ($view instanceof ChoiceGroupView && 0 === \count($view->choices)) {
                    unset($preferredViews[$key]);
                }
            }

            foreach ($otherViews as $key => $view) {
                if ($view instanceof ChoiceGroupView && 0 === \count($view->choices)) {
                    unset($otherViews[$key]);
                }
            }

            foreach ($preferredViewsOrder as $key => $groupViewsOrder) {
                if ($groupViewsOrder) {
                    $preferredViewsOrder[$key] = min($groupViewsOrder);
                } else {
                    unset($preferredViewsOrder[$key]);
                }
            }
        } else {
            // Otherwise use the original structure of the choices
            self::addChoiceViewsFromStructuredValues(
                $list->getStructuredValues(),
                $label,
                $choices,
                $keys,
                $index,
                $attr,
                $preferredChoices,
                $preferredViews,
                $preferredViewsOrder,
                $otherViews
            );
        }

        uksort($preferredViews, static function ($a, $b) use ($preferredViewsOrder): int {
            return isset($preferredViewsOrder[$a], $preferredViewsOrder[$b])
                ? $preferredViewsOrder[$a] <=> $preferredViewsOrder[$b]
                : 0;
        });

        return new ChoiceListView($otherViews, $preferredViews);
    }

    private static function addChoiceView($choice, string $value, $label, array $keys, &$index, $attr, ?callable $isPreferred, array &$preferredViews, array &$preferredViewsOrder, array &$otherViews)
    {
        // $value may be an integer or a string, since it's stored in the array
        // keys. We want to guarantee it's a string though.
        $key = $keys[$value];
        $nextIndex = \is_int($index) ? $index++ : $index($choice, $key, $value);

        // BC normalize label to accept a false value
        if (null === $label) {
            // If the labels are null, use the original choice key by default
            $label = (string) $key;
        } elseif (false !== $label) {
            // If "choice_label" is set to false and "expanded" is true, the value false
            // should be passed on to the "label" option of the checkboxes/radio buttons
            $dynamicLabel = $label($choice, $key, $value);
            $label = false === $dynamicLabel ? false : (string) $dynamicLabel;
        }

        $view = new ChoiceView(
            $choice,
            $value,
            $label,
            // The attributes may be a callable or a mapping from choice indices
            // to nested arrays
            \is_callable($attr) ? $attr($choice, $key, $value) : (isset($attr[$key]) ? $attr[$key] : [])
        );

        // $isPreferred may be null if no choices are preferred
        if (null !== $isPreferred && false !== $preferredKey = $isPreferred($choice, $key, $value)) {
            $preferredViews[$nextIndex] = $view;
            $preferredViewsOrder[$nextIndex] = $preferredKey;
        }

        $otherViews[$nextIndex] = $view;
    }

    private static function addChoiceViewsFromStructuredValues(array $values, $label, array $choices, array $keys, &$index, $attr, ?callable $isPreferred, array &$preferredViews, array &$preferredViewsOrder, array &$otherViews)
    {
        foreach ($values as $key => $value) {
            if (null === $value) {
                continue;
            }

            // Add the contents of groups to new ChoiceGroupView instances
            if (\is_array($value)) {
                $preferredViewsForGroup = [];
                $otherViewsForGroup = [];

                self::addChoiceViewsFromStructuredValues(
                    $value,
                    $label,
                    $choices,
                    $keys,
                    $index,
                    $attr,
                    $isPreferred,
                    $preferredViewsForGroup,
                    $preferredViewsOrder,
                    $otherViewsForGroup
                );

                if (\count($preferredViewsForGroup) > 0) {
                    $preferredViews[$key] = new ChoiceGroupView($key, $preferredViewsForGroup);
                }

                if (\count($otherViewsForGroup) > 0) {
                    $otherViews[$key] = new ChoiceGroupView($key, $otherViewsForGroup);
                }

                continue;
            }

            // Add ungrouped items directly
            self::addChoiceView(
                $choices[$value],
                $value,
                $label,
                $keys,
                $index,
                $attr,
                $isPreferred,
                $preferredViews,
                $preferredViewsOrder,
                $otherViews
            );
        }
    }

    private static function addChoiceViewsGroupedByCallable(callable $groupBy, $choice, string $value, $label, array $keys, &$index, $attr, ?callable $isPreferred, array &$preferredViews, array &$preferredViewsOrder, array &$otherViews)
    {
        $groupLabels = $groupBy($choice, $keys[$value], $value);

        if (null === $groupLabels) {
            // If the callable returns null, don't group the choice
            self::addChoiceView(
                $choice,
                $value,
                $label,
                $keys,
                $index,
                $attr,
                $isPreferred,
                $preferredViews,
                $preferredViewsOrder,
                $otherViews
            );

            return;
        }

        $groupLabels = \is_array($groupLabels) ? array_map('strval', $groupLabels) : [(string) $groupLabels];

        foreach ($groupLabels as $groupLabel) {
            // Initialize the group views if necessary. Unnecessarily built group
            // views will be cleaned up at the end of createView()
            if (!isset($preferredViews[$groupLabel])) {
                $preferredViews[$groupLabel] = new ChoiceGroupView($groupLabel);
                $otherViews[$groupLabel] = new ChoiceGroupView($groupLabel);
            }
            if (!isset($preferredViewsOrder[$groupLabel])) {
                $preferredViewsOrder[$groupLabel] = [];
            }

            self::addChoiceView(
                $choice,
                $value,
                $label,
                $keys,
                $index,
                $attr,
                $isPreferred,
                $preferredViews[$groupLabel]->choices,
                $preferredViewsOrder[$groupLabel],
                $otherViews[$groupLabel]->choices
            );
        }
    }
}
