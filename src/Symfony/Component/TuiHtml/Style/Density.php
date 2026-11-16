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
 * How much room the chrome of a widget takes.
 *
 * A condensed layout drops borders, padding and secondary content so that
 * more fits on a small terminal.
 *
 * @experimental
 */
enum Density: string
{
    case Regular = 'regular';
    case Condensed = 'condensed';
}
