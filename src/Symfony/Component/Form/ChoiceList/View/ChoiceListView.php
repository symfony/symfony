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
    /**
     * The choices.
     *
     * @var ChoiceGroupView[]|ChoiceView[]
     */
    public $choices;

    /**
     * The preferred choices.
     *
     * @var ChoiceGroupView[]|ChoiceView[]
     */
    public $preferredChoices;

    public function __construct(array $choices = array(), array $preferredChoices = array())
    {
        $this->choices = $choices;
        $this->preferredChoices = $preferredChoices;
    }
}
