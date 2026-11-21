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

/**
 * A control with a look of its own (brackets, a box, a marker) that a style provider can take away, like CSS
 * appearance: none, so that only what is styled is drawn.
 *
 * @experimental
 */
interface NativeAppearanceInterface
{
    public function setNativeAppearance(bool $native): void;
}
