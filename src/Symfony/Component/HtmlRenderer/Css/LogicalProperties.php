<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

/** @internal */
final class LogicalProperties
{
    public static function physical(string $property, string $mode, string $direction): string
    {
        $vertical = 'horizontal-tb' !== $mode;
        $sizes = $vertical ? ['inline-size' => 'height', 'block-size' => 'width', 'min-inline-size' => 'min-height', 'max-inline-size' => 'max-height', 'min-block-size' => 'min-width', 'max-block-size' => 'max-width'] : ['inline-size' => 'width', 'block-size' => 'height', 'min-inline-size' => 'min-width', 'max-inline-size' => 'max-width', 'min-block-size' => 'min-height', 'max-block-size' => 'max-height'];
        if (isset($sizes[$property])) {
            return $sizes[$property];
        }
        $sides = $vertical ? ['inline' => 'rtl' === $direction ? ['bottom', 'top'] : ['top', 'bottom'], 'block' => \in_array($mode, ['vertical-lr', 'sideways-lr'], true) ? ['left', 'right'] : ['right', 'left']] : ['inline' => 'rtl' === $direction ? ['right', 'left'] : ['left', 'right'], 'block' => ['top', 'bottom']];
        if (preg_match('/^(margin|padding|inset|border|scroll-margin|scroll-padding)-(inline|block)-(start|end)(-width|-style|-color)?$/', $property, $m)) {
            return ('inset' === $m[1] ? '' : $m[1].'-').$sides[$m[2]]['start' === $m[3] ? 0 : 1].($m[4] ?? '');
        }

        return $property;
    }
}
