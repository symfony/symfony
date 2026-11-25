<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Html\Element;

use Symfony\Bridge\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Widget\CheckboxWidget;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * <input type="checkbox">.
 *
 * @experimental
 */
final class CheckboxElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $checkbox = new CheckboxWidget($element->hasAttribute('checked'), $builder->getAttributeText($element, 'value') ?? 'on');
        FormControlStates::apply($checkbox, $element, false);
        // Checked by default, whatever it is now, like :default
        $checkbox->setState('default', $element->hasAttribute('checked'));
        ConstraintValidation::checkbox($checkbox, $element, $checkbox->isChecked(...));

        return $checkbox;
    }
}
