<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Input;

use Symfony\Component\Tui\Event\MouseEvent;

/**
 * Decodes terminal mouse-reporting sequences into {@see MouseEvent} objects.
 *
 * Supports both the modern SGR encoding (ESC [ < b ; x ; y M/m) and the
 * legacy X10 encoding (ESC [ M cb cx cy). Returns null for any input that
 * is not a complete mouse sequence, so callers can fall back to normal
 * key handling.
 *
 * @experimental
 *
 * @internal
 *
 * @author Louis-Arnaud Catoire <la.catoire@gmail.com>
 */
final class MouseParser
{
    // Modifier flags carried in the button byte.
    private const int FLAG_SHIFT = 4;
    private const int FLAG_ALT = 8;
    private const int FLAG_CTRL = 16;
    private const int FLAG_MOTION = 32;
    private const int FLAG_WHEEL = 64;
    private const int FLAG_EXTRA_BUTTON = 128;

    /**
     * Parse a single input sequence into a MouseEvent, or null if it is not one.
     */
    public function parse(string $data): ?MouseEvent
    {
        // SGR: ESC [ < b ; x ; y (M|m)
        if (preg_match('/^\x1b\[<(\d+);(\d+);(\d+)([Mm])$/', $data, $m)) {
            return $this->build((int) $m[1], (int) $m[2], (int) $m[3], 'm' === $m[4]);
        }

        // X10: ESC [ M followed by exactly three bytes, each offset by 32.
        if (6 === \strlen($data) && str_starts_with($data, "\x1b[M")) {
            return $this->build(
                \ord($data[3]) - 32,
                \ord($data[4]) - 32,
                \ord($data[5]) - 32,
                null,
            );
        }

        return null;
    }

    /**
     * @param int       $cb         the raw button byte (modifier and wheel flags included)
     * @param int       $x          1-indexed column as reported by the terminal
     * @param int       $y          1-indexed row as reported by the terminal
     * @param bool|null $sgrRelease true/false for an SGR release/press, null for X10 (no explicit release)
     */
    private function build(int $cb, int $x, int $y, ?bool $sgrRelease): ?MouseEvent
    {
        $isWheel = (bool) ($cb & self::FLAG_WHEEL);
        $isMotion = (bool) ($cb & self::FLAG_MOTION);
        $low = $cb & 3;

        // Out of scope, ignored rather than reported as a misleading event:
        // horizontal wheel (low 2/3) and the additional buttons 8-11 (flagged
        // by bit 128, e.g. the back/forward side buttons), which would
        // otherwise be decoded as a phantom Left/Middle/Right click.
        if (($isWheel && $low >= 2) || ($cb & self::FLAG_EXTRA_BUTTON)) {
            return null;
        }

        $button = match (true) {
            $isWheel && 0 === $low => MouseButton::WheelUp,
            $isWheel => MouseButton::WheelDown,
            0 === $low => MouseButton::Left,
            1 === $low => MouseButton::Middle,
            2 === $low => MouseButton::Right,
            default => MouseButton::None,
        };

        $kind = match (true) {
            $isWheel => MouseEventKind::Press,
            true === $sgrRelease => MouseEventKind::Release,
            $isMotion => MouseButton::None === $button ? MouseEventKind::Move : MouseEventKind::Drag,
            // X10 reports a release as button code 3 without an explicit release flag.
            null === $sgrRelease && 3 === $low => MouseEventKind::Release,
            default => MouseEventKind::Press,
        };

        return new MouseEvent(
            max(0, $x - 1),
            max(0, $y - 1),
            $button,
            $kind,
            (bool) ($cb & self::FLAG_SHIFT),
            (bool) ($cb & self::FLAG_ALT),
            (bool) ($cb & self::FLAG_CTRL),
        );
    }
}
