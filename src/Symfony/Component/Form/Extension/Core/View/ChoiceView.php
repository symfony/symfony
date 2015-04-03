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
 *
 * @deprecated Deprecated since Symfony 2.7, to be removed in Symfony 3.0.
 *             Use {@link \Symfony\Component\Form\ChoiceList\View\ChoiceView} instead.
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
     * Creates a new ChoiceView.
     *
     * @param mixed  $data  The original choice.
     * @param string $value The view representation of the choice.
     * @param string $label The label displayed to humans.
     */
    public function __construct($data, $value, $label)
    {
        $this->data = $data;
        $this->value = $value;
        $this->label = $label;

        // Trigger deprecation notice unless this is the new ChoiceView class
        if ('Symfony\Component\Form\ChoiceList\View\ChoiceView' !== get_class($this)) {
            trigger_error('The '.__NAMESPACE__.'\ChoiceView class is deprecated since version 2.7 and will be removed in 3.0. Use Symfony\Component\Form\ChoiceList\View\ChoiceView instead.', E_USER_DEPRECATED);
        }
    }
}
