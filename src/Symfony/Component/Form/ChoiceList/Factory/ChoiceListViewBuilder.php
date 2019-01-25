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
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

/**
 * A convenient builder for creating {@link ChoiceListView} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Valentin Udaltsov <udaltsov.valentin@gmail.com>
 *
 * @internal
 */
final class ChoiceListViewBuilder
{
    /**
     * @var array|callable|null
     */
    private $preferredChoices;

    /**
     * @var bool|callable|null
     */
    private $label;

    /**
     * @var int|callable
     */
    private $index = 0;

    /**
     * @var callable|null
     */
    private $groupBy;

    /**
     * @var array|callable|null
     */
    private $attr;

    /**
     * @param array|callable|null $preferredChoices
     */
    public function setPreferredChoices($preferredChoices): self
    {
        $this->preferredChoices = $preferredChoices;

        return $this;
    }

    /**
     * @param bool|callable|null $preferredChoices
     */
    public function setLabel($label): self
    {
        $this->label = $label;

        return $this;
    }

    public function setIndex(?callable $index): self
    {
        $this->index = $index ?? 0;

        return $this;
    }

    public function setGroupBy(?callable $groupBy): self
    {
        $this->groupBy = $groupBy;

        return $this;
    }

    /**
     * @param array|callable|null $attr
     */
    public function setAttr($attr): self
    {
        $this->attr = $attr;

        return $this;
    }

    public function buildForList(ChoiceListInterface $list): ChoiceListView
    {
        $this->resetChoiceIndex();
        $view = new ChoiceListView();

        if (\is_callable($this->groupBy)) {
            $this->addViewsGroupedByCallable(
                $view->preferredChoices,
                $view->choices,
                $list,
                $this->groupBy
            );

            $this->removeEmptyGroupViews($view->preferredChoices);
            $this->removeEmptyGroupViews($view->choices);
        } else {
            $this->addViewsFromStructuredValues(
                $view->preferredChoices,
                $view->choices,
                $list,
                $list->getStructuredValues()
            );
        }

        return $view;
    }

    private function addViewsGroupedByCallable(array &$preferredViews, array &$otherViews, ChoiceListInterface $list, callable $groupBy): void
    {
        $keys = $list->getOriginalKeys();

        foreach ($list->getChoices() as $value => $choice) {
            $value = (string) $value;
            $key = $keys[$value];
            $groupLabel = $groupBy($choice, $key, $value);

            if (null === $groupLabel) {
                $this->addView($preferredViews, $otherViews, $choice, $key, $value);

                continue;
            }

            $groupLabel = (string) $groupLabel;

            if (!isset($preferredViews[$groupLabel])) {
                $preferredViews[$groupLabel] = new ChoiceGroupView($groupLabel);
                $otherViews[$groupLabel] = new ChoiceGroupView($groupLabel);
            }

            $this->addView(
                $preferredViews[$groupLabel]->choices,
                $otherViews[$groupLabel]->choices,
                $choice,
                $key,
                $value
            );
        }
    }

    private function addViewsFromStructuredValues(&$preferredViews, &$otherViews, ChoiceListInterface $list, array $values): void
    {
        $choices = $list->getChoices();

        foreach ($values as $key => $value) {
            if (null === $value) {
                continue;
            }

            if (!\is_array($value)) {
                $this->addView(
                    $preferredViews,
                    $otherViews,
                    $choices[$value],
                    $key,
                    $value
                );

                continue;
            }

            $preferredViewsForGroup = [];
            $otherViewsForGroup = [];

            $this->addViewsFromStructuredValues(
                $preferredViewsForGroup,
                $otherViewsForGroup,
                $list,
                $value
            );

            if (\count($preferredViewsForGroup) > 0) {
                $preferredViews[$key] = new ChoiceGroupView($key, $preferredViewsForGroup);
            }

            if (\count($otherViewsForGroup) > 0) {
                $otherViews[$key] = new ChoiceGroupView($key, $otherViewsForGroup);
            }
        }
    }

    private function addView(array &$preferredViews, array &$otherViews, $choice, $key, $value): void
    {
        $view = new ChoiceView(
            $choice,
            $value,
            $this->getChoiceLabel($choice, $key, $value),
            $this->getChoiceAttr($choice, $key, $value)
        );
        $index = $this->getChoiceIndex($choice, $key, $value);

        if ($this->isChoicePreferred($choice, $key, $value)) {
            $preferredViews[$index] = $view;
        } else {
            $otherViews[$index] = $view;
        }
    }

    private function removeEmptyGroupViews(array &$views): void
    {
        foreach ($views as $key => $view) {
            if ($view instanceof ChoiceGroupView && 0 === \count($view->choices)) {
                unset($views[$key]);
            }
        }
    }

    private function isChoicePreferred($choice, $key, $value): bool
    {
        if (empty($this->preferredChoices)) {
            return false;
        }

        if (\is_callable($this->preferredChoices)) {
            return ($this->preferredChoices)($choice, $key, $value);
        }

        return \in_array($choice, $this->preferredChoices, true);
    }

    private function getChoiceLabel($choice, $key, $value)
    {
        if (null === $this->label) {
            return (string) $key;
        }

        if (false === $this->label) {
            return false;
        }

        $label = ($this->label)($choice, $key, $value);

        return false === $label ? false : (string) $label;
    }

    private function getChoiceIndex($choice, $key, $value)
    {
        if (\is_int($this->index)) {
            return $this->index++;
        }

        return ($this->index)($choice, $key, $value);
    }

    private function resetChoiceIndex(): void
    {
        if (\is_int($this->index)) {
            $this->index = 0;
        }
    }

    private function getChoiceAttr($choice, $key, $value)
    {
        if (\is_callable($this->attr)) {
            return ($this->attr)($choice, $key, $value);
        }

        return $this->attr[$key] ?? [];
    }
}
