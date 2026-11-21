<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/**
 * The color scheme the user prefers, as exposed to `prefers-color-scheme`.
 *
 * @experimental
 */
enum ColorScheme: string
{
    case Light = 'light';
    case Dark = 'dark';
}
