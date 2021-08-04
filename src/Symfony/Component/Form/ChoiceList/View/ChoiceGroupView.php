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
    public $label;
    public $choices;

    /**
     * Creates a new choice group view.
     *
     * @param ChoiceGroupView[]|ChoiceView[] $choices the choice views in the group
     */
    public function __construct(string $label, array $choices = [])
    {
        $this->label = $label;
        $this->choices = $choices;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Traversable<ChoiceGroupView|ChoiceView>
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->choices);
    }
}
