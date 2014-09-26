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
 * Represents a choice in templates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceView
{
    /**
     * The label displayed to humans.
     *
     * @var string
     */
    public $label;

    /**
     * The view representation of the choice.
     *
     * @var string
     */
    public $value;

    /**
     * The original choice value.
     *
     * @var mixed
     */
    public $data;

    /**
     * Additional attributes for the HTML tag.
     *
     * @var array
     */
    public $attr;

    /**
     * Creates a new ChoiceView.
     *
     * @param string $label The label displayed to humans
     * @param string $value The view representation of the choice
     * @param mixed  $data  The original choice
     * @param array  $attr  Additional attributes for the HTML tag
     */
    public function __construct($label, $value, $data, array $attr = array())
    {
        $this->label = $label;
        $this->value = $value;
        $this->data = $data;
        $this->attr = $attr;
    }
}
