<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Internal;

use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Component\Tui\Style\Style as NativeStyle;

/** @internal */
final class WidgetState
{
    public ?Style $style = null;
    public ?NativeStyle $nativeStyle = null;
}
