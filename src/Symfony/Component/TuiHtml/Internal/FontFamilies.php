<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Internal;

use Symfony\Component\Tui\Widget\Figlet\FontRegistry;

/** @internal */
final class FontFamilies
{
    public const GENERIC = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded', 'math', 'emoji', 'fangsong', 'normal', '-apple-system', 'blinkmacsystemfont'];
    private static ?array $bundled = null;

    public static function isBundled(string $family): bool
    {
        return \in_array($family, self::$bundled ??= (new FontRegistry())->getNames(), true);
    }

    public static function first(FontRegistry $registry, string $families): ?string
    {
        foreach (explode(',', $families) as $family) {
            $family = trim($family, " \t\n\"'");
            if ($registry->has($family)) {
                return $family;
            }
            if (\in_array(strtolower($family), self::GENERIC, true)) {
                return null;
            }
        }

        return null;
    }
}
