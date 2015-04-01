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

use Symfony\Component\Form\ChoiceList\View\ChoiceView as BaseChoiceView;

/**
 * Represents a choice in templates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since Symfony 2.7, to be removed in Symfony 3.0.
 *             Use {@link BaseChoiceView} instead.
 */
class ChoiceView extends BaseChoiceView
{
    /**
     * Creates a new ChoiceView.
     *
     * @param mixed  $data  The original choice.
     * @param string $value The view representation of the choice.
     * @param string $label The label displayed to humans.
     */
    public function __construct($data, $value, $label)
    {
        parent::__construct($label, $value, $data);

        trigger_error('The '.__CLASS__.' class is deprecated since version 2.7 and will be removed in 3.0. Use Symfony\Component\Form\ChoiceList\View\ChoiceView instead.', E_USER_DEPRECATED);
    }
}
