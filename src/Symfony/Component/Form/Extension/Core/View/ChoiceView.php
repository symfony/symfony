<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\View;

/**
 * Represents a choice in templates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceView
{
    /**
     * The original choice value.
     *
     * @var mixed
     */
    public $data;

    /**
     * The view representation of the choice.
     *
     * @var string
     */
    public $value;

    /**
     * The label displayed to humans.
     *
     * @var string
     */
    public $label;

    /**
     * Is choice disabled.
     *
     * @var Boolean
     */
    public $disabled;

    /**
     * Creates a new ChoiceView.
     *
     * @param mixed  $data  The original choice.
     * @param string $value The view representation of the choice.
     * @param string $label The label displayed to humans.
     * @param string $disabled Is choice disabled.
     */
    public function __construct($data, $value, $label, $disabled = false)
    {
        $this->data = $data;
        $this->value = $value;
        $this->label = $label;
        $this->disabled = $disabled;
    }
}
