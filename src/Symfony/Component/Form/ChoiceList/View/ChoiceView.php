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
     * Additional attributes for labels HTML tag.
     *
     * @var array
     */
    public $labelAttr;

    /**
     * Additional attributes for labels HTML tag.
     *
     * @var array
     */
    public $labelAttr;

    /**
     * Creates a new choice view.
     *
     * @param mixed  $data      The original choice
     * @param string $value     The view representation of the choice
     * @param string $label     The label displayed to humans
     * @param array  $attr      Additional attributes for the HTML tag
     * @param array  $labelAttr Additional attributes for labels HTML tag
     */
    public function __construct($data, $value, $label, array $attr = array(), array $labelAttr = array())
    {
        $this->data = $data;
        $this->value = $value;
        $this->label = $label;
        $this->attr = $attr;
        $this->labelAttr = $labelAttr;
    }
}
