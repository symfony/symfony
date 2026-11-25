<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Css;

use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\MediaContext;

/** @internal */
final class PropertySyntax
{
    public static function matches(string $syntax, string $value, MediaContext $media): bool
    {
        return \Symfony\Component\HtmlRenderer\Css\PropertySyntax::matches($syntax, $value, static fn (string $color): bool => Value::opaqueColor($color, $media) instanceof Color);
    }
}
