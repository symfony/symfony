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
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\Loader\FilterChoiceLoaderDecorator;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Default implementation of {@link ChoiceListFactoryInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Jules Pietri <jules@heahprod.com>
 */
class DefaultChoiceListFactory implements ChoiceListFactoryInterface
{
    public function createListFromChoices(iterable $choices, ?callable $value = null, ?callable $filter = null): ChoiceListInterface
    {
        if ($filter) {
            // filter the choice list lazily
            return $this->createListFromLoader(new FilterChoiceLoaderDecorator(
                new CallbackChoiceLoader(static fn () => $choices),
                $filter
            ), $value);
        }

        return new ArrayChoiceList($choices, $value);
    }

    public function createListFromLoader(ChoiceLoaderInterface $loader, ?callable $value = null, ?callable $filter = null): ChoiceListInterface
    {
        if ($filter) {
            $loader = new FilterChoiceLoaderDecorator($loader, $filter);
        }

        return new LazyChoiceList($loader, $value);
    }

    /**
     * @param array|callable|null $help
     */
    public function createView(ChoiceListInterface $list, array|callable|null $preferredChoices = null, callable|false|null $label = null, ?callable $index = null, ?callable $groupBy = null, array|callable|null $attr = null, array|callable $labelTranslationParameters = [], bool $duplicatePreferredChoices = true/* , array|callable|null $help = null */): ChoiceListView
    {
        $help = \func_num_args() > 8 ? func_get_arg(8) : null;
        $preferredViews = [];
        $preferredViewsOrder = [];
        $otherViews = [];
        $choices = $list->getChoices();
        $keys = $list->getOriginalKeys();

        if (!\is_callable($preferredChoices)) {
            if (!$preferredChoices) {
                $preferredChoices = null;
            } else {
                // make sure we have keys that reflect order
                $preferredChoices = array_values($preferredChoices);
                $preferredChoices = static fn ($choice) => array_search($choice, $preferredChoices, true);
            }
        }

        // The names are generated from an incrementing integer by default
        $index ??= 0;

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
                    $labelTranslationParameters,
                    $help,
                    $preferredChoices,
                    $preferredViews,
                    $preferredViewsOrder,
                    $otherViews,
                    $duplicatePreferredChoices,
                );
            }

            // Remove empty group views that may have been created by
            // addChoiceViewsGroupedByCallable()
            foreach ($preferredViews as $key => $view) {
                if ($view instanceof ChoiceGroupView && !$view->choices) {
                    unset($preferredViews[$key]);
                }
            }

            foreach ($otherViews as $key => $view) {
                if ($view instanceof ChoiceGroupView && !$view->choices) {
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
                $labelTranslationParameters,
                $help,
                $preferredChoices,
                $preferredViews,
                $preferredViewsOrder,
                $otherViews,
                $duplicatePreferredChoices,
            );
        }

        uksort($preferredViews, static fn ($a, $b) => isset($preferredViewsOrder[$a], $preferredViewsOrder[$b]) ? $preferredViewsOrder[$a] <=> $preferredViewsOrder[$b] : 0);

        return new ChoiceListView($otherViews, $preferredViews);
    }

    /**
     * @param-immediately-invoked-callable $isPreferred
     */
    private static function addChoiceView($choice, string $value, $label, array $keys, &$index, $attr, $labelTranslationParameters, $help, ?callable $isPreferred, array &$preferredViews, array &$preferredViewsOrder, array &$otherViews, bool $duplicatePreferredChoices): void
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

            if (false === $dynamicLabel) {
                $label = false;
            } elseif ($dynamicLabel instanceof TranslatableInterface) {
                $label = $dynamicLabel;
            } else {
                $label = (string) $dynamicLabel;
            }
        }

        if (\is_callable($help)) {
            $help = $help($choice, $key, $value);
        } else {
            $help = $help[$key] ?? null;
        }

        if (null !== $help && !$help instanceof TranslatableInterface) {
            $help = (string) $help;
        }

        $view = new ChoiceView(
            $choice,
            $value,
            $label,
            // The attributes may be a callable or a mapping from choice indices
            // to nested arrays
            \is_callable($attr) ? $attr($choice, $key, $value) : ($attr[$key] ?? []),
            // The label translation parameters may be a callable or a mapping from choice indices
            // to nested arrays
            \is_callable($labelTranslationParameters) ? $labelTranslationParameters($choice, $key, $value) : ($labelTranslationParameters[$key] ?? []),
            $help,
        );

        // $isPreferred may be null if no choices are preferred
        if (null !== $isPreferred && false !== $preferredKey = $isPreferred($choice, $key, $value)) {
            $preferredViews[$nextIndex] = $view;
            $preferredViewsOrder[$nextIndex] = $preferredKey;

            if ($duplicatePreferredChoices) {
                $otherViews[$nextIndex] = $view;
            }
        } else {
            $otherViews[$nextIndex] = $view;
        }
    }

    private static function addChoiceViewsFromStructuredValues(array $values, $label, array $choices, array $keys, &$index, $attr, $labelTranslationParameters, $help, ?callable $isPreferred, array &$preferredViews, array &$preferredViewsOrder, array &$otherViews, bool $duplicatePreferredChoices): void
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
                    $labelTranslationParameters,
                    $help,
                    $isPreferred,
                    $preferredViewsForGroup,
                    $preferredViewsOrder,
                    $otherViewsForGroup,
                    $duplicatePreferredChoices,
                );

                if ($preferredViewsForGroup) {
                    $preferredViews[$key] = new ChoiceGroupView($key, $preferredViewsForGroup);
                }

                if ($otherViewsForGroup) {
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
                $labelTranslationParameters,
                $help,
                $isPreferred,
                $preferredViews,
                $preferredViewsOrder,
                $otherViews,
                $duplicatePreferredChoices,
            );
        }
    }

    /**
     * @param-immediately-invoked-callable $groupBy
     * @param-immediately-invoked-callable $isPreferred
     */
    private static function addChoiceViewsGroupedByCallable(callable $groupBy, $choice, string $value, $label, array $keys, &$index, $attr, $labelTranslationParameters, $help, ?callable $isPreferred, array &$preferredViews, array &$preferredViewsOrder, array &$otherViews, bool $duplicatePreferredChoices): void
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
                $labelTranslationParameters,
                $help,
                $isPreferred,
                $preferredViews,
                $preferredViewsOrder,
                $otherViews,
                $duplicatePreferredChoices,
            );

            return;
        }

        $groupLabels = \is_array($groupLabels) ? $groupLabels : [$groupLabels];

        foreach ($groupLabels as $groupLabel) {
            if (!$groupLabel instanceof TranslatableInterface) {
                $groupLabel = (string) $groupLabel;
            }

            $groupKey = self::getGroupKey($groupLabel, $preferredViews);

            // Initialize the group views if necessary. Unnecessarily built group
            // views will be cleaned up at the end of createView()
            if (!isset($preferredViews[$groupKey])) {
                $preferredViews[$groupKey] = new ChoiceGroupView($groupLabel);
                $otherViews[$groupKey] = new ChoiceGroupView($groupLabel);
            }
            if (!isset($preferredViewsOrder[$groupKey])) {
                $preferredViewsOrder[$groupKey] = [];
            }

            self::addChoiceView(
                $choice,
                $value,
                $label,
                $keys,
                $index,
                $attr,
                $labelTranslationParameters,
                $help,
                $isPreferred,
                $preferredViews[$groupKey]->choices,
                $preferredViewsOrder[$groupKey],
                $otherViews[$groupKey]->choices,
                $duplicatePreferredChoices,
            );
        }
    }

    /**
     * Translatable group labels cannot be used as array keys. An opaque key is
     * generated for them instead, reusing the one of an equivalent label so that
     * choices sharing the same group label end up in the same group view.
     *
     * @param array<ChoiceGroupView|ChoiceView> $views
     */
    private static function getGroupKey(string|TranslatableInterface $groupLabel, array $views): string
    {
        if (!$groupLabel instanceof TranslatableInterface) {
            return $groupLabel;
        }

        foreach ($views as $key => $view) {
            if ($view instanceof ChoiceGroupView && $view->label instanceof TranslatableInterface && $view->label == $groupLabel) {
                return $key;
            }
        }

        // Prefixing with a NUL byte avoids clashing with the integer keys of ungrouped choices
        $i = \count($views);
        while (isset($views["\0".$i])) {
            ++$i;
        }

        return "\0".$i;
    }
}
