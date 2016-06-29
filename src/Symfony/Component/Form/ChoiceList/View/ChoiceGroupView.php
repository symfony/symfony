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
 * Represents a group of choices in templates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceGroupView implements \IteratorAggregate
{
    /**
     * The label of the group.
     *
     * @var string
     */
    public $label;

    /**
     * The choice views in the group.
     *
     * @var ChoiceGroupView[]|ChoiceView[]
     */
    public $choices;

    /**
     * Creates a new choice group view.
     *
     * @param string                         $label   The label of the group
     * @param ChoiceGroupView[]|ChoiceView[] $choices The choice views in the
     *                                                group.
     */
    public function __construct($label, array $choices = array())
    {
        $this->label = $label;
        $this->choices = $choices;
    }

    /**
     * {@inheritdoc}
     *
     * @return ChoiceGroupView[]|ChoiceView[]
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->choices);
    }
}
