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

use Symfony\Component\Form\Extension\Core\View\ChoiceView as LegacyChoiceView;

/**
 * Represents a choice in templates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceView extends LegacyChoiceView
{
    /**
     * Additional attributes for the HTML tag.
     *
     * @var array
     */
    public $attr;

    /**
     * Creates a new choice view.
     *
     * @param string $label The label displayed to humans
     * @param string $value The view representation of the choice
     * @param mixed  $data  The original choice
     * @param array  $attr  Additional attributes for the HTML tag
     */
    public function __construct($label, $value, $data, array $attr = array())
    {
        parent::__construct($data, $value, $label);

        $this->attr = $attr;
    }
}
