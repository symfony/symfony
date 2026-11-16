<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Html\Element;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Widget\RadioGroup;
use Symfony\Component\TuiHtml\Widget\RadioWidget;

/**
 * <input type="radio">: radios sharing a name in a form, or outside any form in a document, form a group.
 *
 * @experimental
 */
final class RadioElement implements ElementFactoryInterface
{
    /** @var \WeakMap<\Dom\Node, array<string, RadioGroup>> The groups of each form, or of the document for the radios outside forms */
    private \WeakMap $groups;

    public function __construct()
    {
        $this->groups = new \WeakMap();
    }

    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $name = $element->getAttribute('name');
        $group = null;
        if (null !== $name && '' !== $name) {
            $owner = $element->closest('form') ?? $element->ownerDocument;
            $groups = $this->groups[$owner] ?? [];
            $group = $groups[$name] ??= new RadioGroup($name);
            $this->groups[$owner] = $groups;
        }
        $radio = new RadioWidget($element->hasAttribute('checked'), $builder->getAttributeText($element, 'value') ?? 'on', $group);
        FormControlStates::apply($radio, $element, false);
        // Checked by default, whatever it is now, like :default
        $radio->setState('default', $element->hasAttribute('checked'));

        return $radio;
    }
}
