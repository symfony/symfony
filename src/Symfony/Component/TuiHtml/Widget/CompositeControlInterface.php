<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget;

use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * A form control drawn with parts around it, like the list of a combobox or the swatch of a color field: the
 * element it is built from, its id, classes, style and value, are those of the control.
 *
 * @experimental
 */
interface CompositeControlInterface
{
    public function getControl(): AbstractWidget;
}
