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
 *
 * @implements \IteratorAggregate<array-key, ChoiceGroupView|ChoiceView>
 */
class ChoiceGroupView implements \IteratorAggregate
{
    public string $label;
    public array $choices;

    /**
     * Creates a new choice group view.
     *
     * @param array<ChoiceGroupView|ChoiceView> $choices the choice views in the group
     */
    public function __construct(string $label, array $choices = [])
    {
        $this->label = $label;
        $this->choices = $choices;
    }

    /**
     * @return \Traversable<array-key, ChoiceGroupView|ChoiceView>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->choices);
    }
}
