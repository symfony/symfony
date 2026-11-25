<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Compatibility;

use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Component\Tui\Ansi\AnsiCodeTracker;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

/** A visual comparison under an explicit terminal palette/default-color assumption. */
final class TerminalCellSnapshot
{
    public static function normalize(string $snapshot, int $columns, int $rows, array $foreground = [255, 255, 255], array $background = [0, 0, 0]): array
    {
        $screen = new ScreenBuffer($columns, $rows + 1);
        $screen->write(str_replace('⟨', "\x1b[", $snapshot));
        $result = [];
        $styles = [];
        foreach ($screen->getCells() as $row => $cells) {
            foreach ($cells as $column => $cell) {
                $style = $styles[$cell['style']] ??= self::style($cell['style'], $foreground, $background);
                $character = $cell['char'];
                if (' ' === $character) {
                    // Foreground on an undecorated blank cell has no visible effect.
                    if (!preg_match('/(?:^|;)(?:4(?::\d+)?|9|21|53)(?:;|$)/', $style[2])) {
                        $style[0] = null;
                        $style[2] = '';
                    }
                    if (null === $style[0] && $style[1] === $background) {
                        continue;
                    }
                }
                $result[$row][$column] = [$character, ...$style];
            }
        }

        return $result;
    }

    private static function style(string $codes, array $foreground, array $background): array
    {
        $tracker = new AnsiCodeTracker();
        $tracker->processText($codes);
        $fg = self::color($tracker->getForeground(), $foreground, false);
        $bg = self::color($tracker->getBackground(), $background, true);
        if ($tracker->isInverse()) {
            [$fg, $bg] = [$bg, $fg];
        }
        if ($tracker->isHidden()) {
            $fg = $bg;
        }
        $active = $tracker->getActiveCodes();
        preg_match('/\x1b\[([0-9;:]*)m/', $active, $match);
        $parameters = explode(';', $match[1] ?? '');
        $attributes = [];
        for ($index = 0; $index < \count($parameters); ++$index) {
            $parameter = $parameters[$index];
            if (\in_array($parameter, ['38', '48', '58'], true)) {
                $length = '2' === ($parameters[$index + 1] ?? '') ? 5 : 3;
                if ('58' === $parameter) {
                    $attributes[] = 'underline-color:'.implode(',', self::color(implode(';', \array_slice($parameters, $index, $length)), $fg, false));
                }
                $index += $length - 1;
            } elseif (!preg_match('/^(?:3[0-7]|4[0-7]|9[0-7]|10[0-7]|7|8)$/', $parameter) && '' !== $parameter) {
                $attributes[] = $parameter;
            }
        }
        $attributes = implode(';', $attributes);

        return [$fg, $bg, $attributes];
    }

    private static function color(?string $parameters, array $default, bool $background): array
    {
        if (null === $parameters) {
            return $default;
        }
        $parts = array_map('intval', explode(';', $parameters));
        if (\count($parts) >= 5 && 2 === $parts[1]) {
            return \array_slice($parts, 2, 3);
        }
        if (\count($parts) >= 3 && 5 === $parts[1]) {
            return array_values(Color::palette($parts[2])->toRgb());
        }
        $color = $background ? Color::fromSgrBackground($parts[0]) : Color::fromSgrForeground($parts[0]);

        return null === $color ? $default : array_values($color->toRgb());
    }
}
