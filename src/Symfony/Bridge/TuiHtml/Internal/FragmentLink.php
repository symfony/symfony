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

/** @internal Printable local link targets accepted by the terminal link protocol. */
final class FragmentLink
{
    public static function target(string $href): ?string
    {
        $href = trim($href);

        return preg_match('/^#[^\\x00-\\x20\\x7f;\\e]{0,256}$/', $href) ? $href : null;
    }
}
