<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\ChoiceList\View;

/**
 * Represents a group of choices in templates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceGroupView implements \IteratorAggregate
{
    public $label;
    public $choices;

    /**
     * Creates a new choice group view.
     *
     * @param string                         $label   The label of the group
     * @param ChoiceGroupView[]|ChoiceView[] $choices the choice views in the group
     */
    public function __construct($label, array $choices = array())
    {
        $this->label = $label;
        $this->choices = $choices;
    }

    /**
     * {@inheritdoc}
     *
     * @return self[]|ChoiceView[]
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->choices);
    }
}
