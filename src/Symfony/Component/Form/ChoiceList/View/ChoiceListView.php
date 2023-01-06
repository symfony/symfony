<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\View;

/**
 * Represents a choice list in templates.
 *
 * A choice list contains choices and optionally preferred choices which are
 * displayed in the very beginning of the list. Both choices and preferred
 * choices may be grouped in {@link ChoiceGroupView} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceListView
{
    public $choices;
    public $preferredChoices;

    /**
     * Creates a new choice list view.
     *
     * @param ChoiceGroupView[]|ChoiceView[] $choices          The choice views
     * @param ChoiceGroupView[]|ChoiceView[] $preferredChoices the preferred choice views
     */
    public function __construct(array $choices = [], array $preferredChoices = [])
    {
        $this->choices = $choices;
        $this->preferredChoices = $preferredChoices;
    }

    /**
     * Returns whether a placeholder is in the choices.
     *
     * A placeholder must be the first child element, not be in a group and have an empty value.
     */
    public function hasPlaceholder(): bool
    {
        if ($this->preferredChoices) {
            $firstChoice = reset($this->preferredChoices);

            return $firstChoice instanceof ChoiceView && '' === $firstChoice->value;
        }

        $firstChoice = reset($this->choices);

        return $firstChoice instanceof ChoiceView && '' === $firstChoice->value;
    }
}
