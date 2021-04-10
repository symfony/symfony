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

use Symfony\Component\Translation\TranslatableMessage;

/**
 * Represents a choice in templates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceView
{
    public $label;
    public $value;
    public $data;

    /**
     * Additional attributes for the HTML tag.
     */
    public $attr;

    /**
     * Creates a new choice view.
     *
     * @param mixed                            $data  The original choice
     * @param string                           $value The view representation of the choice
     * @param string|TranslatableMessage|false $label The label displayed to humans; pass false to discard the label
     * @param array                            $attr  Additional attributes for the HTML tag
     */
    public function __construct($data, string $value, $label, array $attr = [])
    {
        $this->data = $data;
        $this->value = $value;
        $this->label = $label;
        $this->attr = $attr;
    }
}
