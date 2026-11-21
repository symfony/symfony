<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Html;

use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Creates the widget an HTML element stands for.
 *
 * The builder applies the global attributes (id, class, style, title,
 * aria-*...) to the widget; a factory only handles what is specific to its
 * element, like the value of an <input>.
 *
 * @experimental
 */
interface ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget;
}
