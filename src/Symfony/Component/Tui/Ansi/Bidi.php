<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Ansi;

/**
 * The Unicode Bidirectional Algorithm (UAX #9): lines of text stored in logical order, drawn in visual order.
 *
 * The bidirectional classes, brackets and mirrored characters come from
 * IntlChar, of ext-intl or symfony/polyfill-intl-char: without it, the
 * text is drawn as stored.
 *
 * A line is reordered on its own, its paragraph level given: the explicit
 * embeddings, overrides and isolates open at the end of a line are opened
 * again at the start of the next one by the caller (see openControls()).
 *
 * @experimental
 */
final class Bidi
{
    private const MAX_DEPTH = 125;

    /** The classes of IntlChar::charDirection(), by value */
    private const CLASSES = ['L', 'R', 'EN', 'ES', 'ET', 'AN', 'CS', 'B', 'S', 'WS', 'ON', 'LRE', 'LRO', 'AL', 'RLE', 'RLO', 'PDF', 'NSM', 'BN', 'FSI', 'LRI', 'RLI', 'PDI'];

    /** The characters that may have to be reordered, and the controls of embeddings, overrides and isolates */
    private const REORDERED = '/[\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFC}\x{10800}-\x{10FFF}\x{1E800}-\x{1EFFF}\x{200F}\x{061C}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    /**
     * The presentation forms of the Arabic letters, from UnicodeData.txt: isolated, final, initial and medial, null
     * where the letter has none (it does not join on that side).
     */
    private const ARABIC_FORMS = [
        0x0621 => [0xFE80, null, null, null],
        0x0622 => [0xFE81, 0xFE82, null, null],
        0x0623 => [0xFE83, 0xFE84, null, null],
        0x0624 => [0xFE85, 0xFE86, null, null],
        0x0625 => [0xFE87, 0xFE88, null, null],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E, null, null],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94, null, null],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA, null, null],
        0x0630 => [0xFEAB, 0xFEAC, null, null],
        0x0631 => [0xFEAD, 0xFEAE, null, null],
        0x0632 => [0xFEAF, 0xFEB0, null, null],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE, null, null],
        0x0649 => [0xFEEF, 0xFEF0, 0xFBE8, 0xFBE9],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        0x0671 => [0xFB50, 0xFB51, null, null],
        0x0677 => [0xFBDD, null, null, null],
        0x0679 => [0xFB66, 0xFB67, 0xFB68, 0xFB69],
        0x067A => [0xFB5E, 0xFB5F, 0xFB60, 0xFB61],
        0x067B => [0xFB52, 0xFB53, 0xFB54, 0xFB55],
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59],
        0x067F => [0xFB62, 0xFB63, 0xFB64, 0xFB65],
        0x0680 => [0xFB5A, 0xFB5B, 0xFB5C, 0xFB5D],
        0x0683 => [0xFB76, 0xFB77, 0xFB78, 0xFB79],
        0x0684 => [0xFB72, 0xFB73, 0xFB74, 0xFB75],
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        0x0687 => [0xFB7E, 0xFB7F, 0xFB80, 0xFB81],
        0x0688 => [0xFB88, 0xFB89, null, null],
        0x068C => [0xFB84, 0xFB85, null, null],
        0x068D => [0xFB82, 0xFB83, null, null],
        0x068E => [0xFB86, 0xFB87, null, null],
        0x0691 => [0xFB8C, 0xFB8D, null, null],
        0x0698 => [0xFB8A, 0xFB8B, null, null],
        0x06A4 => [0xFB6A, 0xFB6B, 0xFB6C, 0xFB6D],
        0x06A6 => [0xFB6E, 0xFB6F, 0xFB70, 0xFB71],
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],
        0x06AD => [0xFBD3, 0xFBD4, 0xFBD5, 0xFBD6],
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        0x06B1 => [0xFB9A, 0xFB9B, 0xFB9C, 0xFB9D],
        0x06B3 => [0xFB96, 0xFB97, 0xFB98, 0xFB99],
        0x06BA => [0xFB9E, 0xFB9F, null, null],
        0x06BB => [0xFBA0, 0xFBA1, 0xFBA2, 0xFBA3],
        0x06BE => [0xFBAA, 0xFBAB, 0xFBAC, 0xFBAD],
        0x06C0 => [0xFBA4, 0xFBA5, null, null],
        0x06C1 => [0xFBA6, 0xFBA7, 0xFBA8, 0xFBA9],
        0x06C5 => [0xFBE0, 0xFBE1, null, null],
        0x06C6 => [0xFBD9, 0xFBDA, null, null],
        0x06C7 => [0xFBD7, 0xFBD8, null, null],
        0x06C8 => [0xFBDB, 0xFBDC, null, null],
        0x06C9 => [0xFBE2, 0xFBE3, null, null],
        0x06CB => [0xFBDE, 0xFBDF, null, null],
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
        0x06D0 => [0xFBE4, 0xFBE5, 0xFBE6, 0xFBE7],
        0x06D2 => [0xFBAE, 0xFBAF, null, null],
        0x06D3 => [0xFBB0, 0xFBB1, null, null],
    ];

    /** @var array<int, string> */
    private static array $classes = [];
    /** @var array<string, string> The lines reordered, by their paragraph level and their text */
    private static array $reordered = [];

    /** Whether the lines are drawn in visual order, null to decide from the terminal (see setEnabled()) */
    private static ?bool $enabled = null;

    /**
     * Whether the lines are drawn in visual order: with IntlChar, unless the terminal reorders them itself.
     */
    public static function isAvailable(): bool
    {
        return class_exists(\IntlChar::class) && (self::$enabled ?? !self::terminalReorders());
    }

    /**
     * Draws the lines in visual order or as stored, whatever the terminal; null decides from the terminal again.
     *
     * Terminals that reorder bidirectional text themselves, whatever an application asks (like Terminal.app,
     * Konsole and mlterm), would reverse the lines drawn in visual order again: the lines are drawn as stored there.
     */
    public static function setEnabled(?bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    private static function terminalReorders(): bool
    {
        return 'Apple_Terminal' === getenv('TERM_PROGRAM') || '' !== (string) getenv('KONSOLE_VERSION') || str_starts_with((string) getenv('TERM'), 'mlterm');
    }

    /**
     * Whether a line in a paragraph of that level may be drawn otherwise than as stored.
     *
     * @internal
     */
    public static function needsReordering(string $line, int $level): bool
    {
        return 1 === $level % 2 || 1 === preg_match(self::REORDERED, $line);
    }

    /**
     * Whether a text has right-to-left characters, like the letters of Arabic and Hebrew: the bidirectional controls
     * alone, like those typed in a field, do not count.
     *
     * @internal
     */
    public static function hasRightToLeft(string $text): bool
    {
        return 1 === preg_match('/[\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFC}\x{10800}-\x{10FFF}\x{1E800}-\x{1EFFF}]/u', $text);
    }

    /**
     * The direction of the first strong character of a text, like the paragraph level of P2 and P3: 0 left to right,
     * 1 right to left, null without one.
     *
     * @internal
     */
    public static function direction(string $text): ?int
    {
        $classes = [];
        foreach (mb_str_split(AnsiUtils::stripAnsiCodes($text)) as $character) {
            $classes[] = self::classOf($character);
        }

        return self::firstStrong($classes, 0, \count($classes));
    }

    /**
     * The controls of the embeddings, overrides and isolates still open at the end of a text, outermost first.
     *
     * @internal
     */
    public static function openControls(string $text): string
    {
        if (!preg_match_all('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $text, $matches)) {
            return '';
        }
        $stack = [];
        foreach ($matches[0] as $control) {
            if ("\u{202C}" === $control) {
                // PDF: the last embedding or override, not past an isolate
                if ($stack && !\in_array(end($stack), ["\u{2066}", "\u{2067}", "\u{2068}"], true)) {
                    array_pop($stack);
                }
            } elseif ("\u{2069}" === $control) {
                // PDI: down to the last isolate
                while ($stack && !\in_array(array_pop($stack), ["\u{2066}", "\u{2067}", "\u{2068}"], true)) {
                }
            } else {
                $stack[] = $control;
            }
        }

        return implode('', $stack);
    }

    /**
     * Reorders a line of text with ANSI codes: its characters in visual order, each in its colors and attributes and
     * its link (OSC 8), mirrored in a right-to-left run (like the brackets), the bidirectional controls taken out.
     *
     * @param int $level The paragraph level: 0 left to right, 1 right to left
     *
     * @internal
     */
    public static function reorderLine(string $line, int $level): string
    {
        // The same lines come back as the widgets are drawn again: reordered once
        $key = $level.':'.$line;
        if (isset(self::$reordered[$key])) {
            return self::$reordered[$key];
        }
        if (\count(self::$reordered) >= 2048) {
            self::$reordered = [];
        }

        return self::$reordered[$key] = self::reorder($line, $level);
    }

    private static function reorder(string $line, int $level): string
    {
        // The graphemes of the line, each with the codes active on it and those before it
        $units = [];
        $codes = new AnsiCodeTracker();
        $link = '';
        $pending = '';
        $length = \strlen($line);
        for ($i = 0; $i < $length;) {
            if ("\x1b" === $line[$i] && null !== $code = AnsiUtils::extractAnsiCode($line, $i)) {
                $i += $code['length'];
                $code = $code['code'];
                if (str_starts_with($code, "\x1b[") && str_ends_with($code, 'm')) {
                    $codes->process($code);
                } elseif (str_starts_with($code, "\x1b]8;")) {
                    // A link: open until one with no address
                    $link = preg_match('/^\x1b\]8;[^;]*;(?:\x07|\x1b\\\\)$/', $code) ? '' : $code;
                } else {
                    $pending .= $code;
                }
                continue;
            }
            $next = strpos($line, "\x1b", $i);
            $text = substr($line, $i, (false === $next ? $length : $next) - $i);
            $i += \strlen($text);
            foreach (grapheme_str_split($text) ?: [$text] as $grapheme) {
                $units[] = ['text' => $grapheme, 'codes' => $codes->getActiveCodes(), 'link' => $link, 'marks' => $pending];
                $pending = '';
            }
        }
        if (!$units) {
            return $line;
        }

        // Arabic letters as the forms they take in their words: the terminals that do not reorder lines join the letters
        // in the order they are drawn, from the left, which is not theirs
        if (preg_match('/[\x{0620}-\x{06FF}]/u', $line)) {
            self::shapeArabic($units);
        }

        $types = array_map(static fn (array $unit): string => self::classOf($unit['text']), $units);
        $levels = self::resolveLevels($types, $level, array_map(static fn (array $unit): string => $unit['text'], $units));

        // The controls and the characters X9 takes out are not drawn: what is before them goes with the next one
        $kept = [];
        $carry = '';
        foreach ($units as $index => $unit) {
            if (null === $levels[$index]) {
                $carry .= $unit['marks'];
                continue;
            }
            $units[$index]['marks'] = $carry.$unit['marks'];
            $carry = '';
            $kept[] = $index;
        }
        if (!$kept) {
            return $pending.$carry;
        }

        $order = self::visualOrder($levels);

        // The spans marked by OSC 5113 r<id> and R<id> (the bases of ruby): marked again around where their characters went
        $after = [];
        if (str_contains(implode('', array_column($units, 'marks')).$carry.$pending, "\x1b]5113;")) {
            $spans = [];
            foreach ($units as $index => $unit) {
                if (!preg_match_all('/\x1b\]5113;([rR])(\d+)\x07/', $unit['marks'], $matches, \PREG_SET_ORDER)) {
                    continue;
                }
                foreach ($matches as [$marker, $kind, $id]) {
                    $units[$index]['marks'] = str_replace($marker, '', $units[$index]['marks']);
                    $spans[$id][$kind] = 'r' === $kind ? $index : $index - 1;
                }
            }
            foreach (['carry', 'pending'] as $rest) {
                if (preg_match_all('/\x1b\]5113;R(\d+)\x07/', $$rest, $matches, \PREG_SET_ORDER)) {
                    foreach ($matches as [$marker, $id]) {
                        $$rest = str_replace($marker, '', $$rest);
                        $spans[$id]['R'] = \count($units) - 1;
                    }
                }
            }
            $at = array_flip($order);
            foreach ($spans as $id => $span) {
                $positions = [];
                for ($index = $span['r'] ?? 0; $index <= ($span['R'] ?? \count($units) - 1); ++$index) {
                    if (isset($at[$index])) {
                        $positions[] = $at[$index];
                    }
                }
                if ($positions) {
                    $units[$order[min($positions)]]['marks'] = "\x1b]5113;r{$id}\x07".$units[$order[min($positions)]]['marks'];
                    $after[$order[max($positions)]] = ($after[$order[max($positions)]] ?? '')."\x1b]5113;R{$id}\x07";
                }
            }
        }

        $visual = '';
        $active = '';
        $openLink = '';
        foreach ($order as $index) {
            $unit = $units[$index];
            if ($unit['link'] !== $openLink) {
                $visual .= ('' !== $openLink ? "\x1b]8;;\x07" : '').$unit['link'];
                $openLink = $unit['link'];
            }
            if ($unit['codes'] !== $active) {
                $visual .= ('' !== $active ? "\x1b[0m" : '').$unit['codes'];
                $active = $unit['codes'];
            }
            // The isolates and the marks (LRM, RLM, ALM) are not drawn either, once ordered
            $text = preg_match('/^[\x{2066}-\x{2069}\x{200E}\x{200F}\x{061C}]$/u', $unit['text']) ? '' : $unit['text'];
            // L4: in a right-to-left run, a mirrored character is drawn as its mirror, like ( as )
            if ('' !== $text && 1 === $levels[$index] % 2 && \IntlChar::isMirrored($first = mb_substr($text, 0, 1)) && null !== $mirror = \IntlChar::charMirror($first)) {
                $text = $mirror.mb_substr($text, 1);
            }
            $visual .= $unit['marks'].$text.($after[$index] ?? '');
        }

        return $visual.('' !== $active ? "\x1b[0m" : '').('' !== $openLink ? "\x1b]8;;\x07" : '').$carry.$pending;
    }

    /**
     * L2: the positions of the characters of a line in visual order, from their levels, those taken out (null) left
     * out: from the highest level to the lowest odd one, each run at that level or higher reversed.
     *
     * @param list<int|null> $levels
     *
     * @return list<int>
     *
     * @internal
     */
    public static function visualOrder(array $levels): array
    {
        $order = array_keys(array_filter($levels, static fn (?int $level) => null !== $level));
        if (!$order) {
            return [];
        }
        $highest = max(array_map(static fn (int $index) => $levels[$index], $order));
        $lowestOdd = min(array_map(static fn (int $index) => $levels[$index] | 1, $order));
        for ($at = $highest; $at >= $lowestOdd; --$at) {
            for ($i = 0, $count = \count($order); $i < $count;) {
                if ($levels[$order[$i]] < $at) {
                    ++$i;
                    continue;
                }
                $end = $i;
                while ($end < $count && $levels[$order[$end]] >= $at) {
                    ++$end;
                }
                array_splice($order, $i, $end - $i, array_reverse(\array_slice($order, $i, $end - $i)));
                $i = $end;
            }
        }

        return array_values($order);
    }

    /**
     * Replaces the Arabic letters with their presentation forms: final after a letter that joins the next one,
     * initial before a letter that joins the previous one, medial between them, isolated otherwise.
     *
     * @param list<array{text: string, codes: string, link: string, marks: string}> $units
     */
    private static function shapeArabic(array &$units): void
    {
        // How each joins: D on both sides, R to the previous letter only, C causing joins (tatweel, ZWJ), U not at all
        $joining = array_map(static function (array $unit): string {
            $codePoint = mb_ord($unit['text']) ?: 0;
            if (isset(self::ARABIC_FORMS[$codePoint])) {
                return null !== self::ARABIC_FORMS[$codePoint][2] ? 'D' : (null !== self::ARABIC_FORMS[$codePoint][1] ? 'R' : 'U');
            }

            return 0x0640 === $codePoint || 0x200D === $codePoint ? 'C' : 'U';
        }, $units);

        foreach ($units as $i => $unit) {
            if ('D' !== $joining[$i] && 'R' !== $joining[$i]) {
                continue;
            }
            $forms = self::ARABIC_FORMS[mb_ord($unit['text'])];
            $previous = \in_array($joining[$i - 1] ?? 'U', ['D', 'C'], true);
            $next = 'D' === $joining[$i] && \in_array($joining[$i + 1] ?? 'U', ['D', 'R', 'C'], true);
            $form = match (true) {
                $previous && $next && null !== $forms[3] => $forms[3],
                $previous && null !== $forms[1] => $forms[1],
                $next && null !== $forms[2] => $forms[2],
                default => $forms[0],
            };
            if (null !== $form) {
                $units[$i]['text'] = mb_chr($form).mb_substr($unit['text'], 1);
            }
        }
    }

    /**
     * Resolves the embedding level of each character of a line, null for those X9 takes out.
     *
     * @param list<string> $types      The bidirectional class of each character
     * @param list<string> $characters The characters, for their brackets
     *
     * @return list<int|null>
     *
     * @internal
     */
    public static function resolveLevels(array $types, int $paragraphLevel, array $characters = []): array
    {
        $count = \count($types);
        $original = $types;
        $levels = array_fill(0, $count, $paragraphLevel);
        $matchingPdi = self::matchingPdis($types);

        // X1-X8: the explicit embeddings, overrides and isolates
        $stack = [[$paragraphLevel, null, false]];
        $overflowIsolates = 0;
        $overflowEmbeddings = 0;
        $validIsolates = 0;
        $removed = [];
        for ($i = 0; $i < $count; ++$i) {
            [$current, $override] = $stack[\count($stack) - 1];
            $type = $types[$i];
            switch ($type) {
                case 'RLE':
                case 'LRE':
                case 'RLO':
                case 'LRO':
                    $rtl = 'R' === $type[0];
                    $next = $rtl ? ($current + 1) | 1 : ($current + 2) & ~1;
                    if ($next <= self::MAX_DEPTH && 0 === $overflowIsolates && 0 === $overflowEmbeddings) {
                        $stack[] = [$next, 'O' === $type[2] ? ($rtl ? 'R' : 'L') : null, false];
                    } elseif (0 === $overflowIsolates) {
                        ++$overflowEmbeddings;
                    }
                    $removed[$i] = true;
                    break;
                case 'RLI':
                case 'LRI':
                case 'FSI':
                    $levels[$i] = $current;
                    if (null !== $override) {
                        $types[$i] = $override;
                    }
                    $rtl = 'RLI' === $type || ('FSI' === $type && 1 === self::firstStrong($original, $i + 1, $matchingPdi[$i] ?? $count));
                    $next = $rtl ? ($current + 1) | 1 : ($current + 2) & ~1;
                    if ($next <= self::MAX_DEPTH && 0 === $overflowIsolates && 0 === $overflowEmbeddings) {
                        ++$validIsolates;
                        $stack[] = [$next, null, true];
                    } else {
                        ++$overflowIsolates;
                    }
                    break;
                case 'PDI':
                    if ($overflowIsolates > 0) {
                        --$overflowIsolates;
                    } elseif ($validIsolates > 0) {
                        $overflowEmbeddings = 0;
                        while (!$stack[\count($stack) - 1][2]) {
                            array_pop($stack);
                        }
                        array_pop($stack);
                        --$validIsolates;
                    }
                    [$current, $override] = $stack[\count($stack) - 1];
                    $levels[$i] = $current;
                    if (null !== $override) {
                        $types[$i] = $override;
                    }
                    break;
                case 'PDF':
                    if (0 === $overflowIsolates) {
                        if ($overflowEmbeddings > 0) {
                            --$overflowEmbeddings;
                        } elseif (!$stack[\count($stack) - 1][2] && \count($stack) >= 2) {
                            array_pop($stack);
                        }
                    }
                    $removed[$i] = true;
                    break;
                case 'B':
                    $levels[$i] = $paragraphLevel;
                    break;
                case 'BN':
                    $removed[$i] = true;
                    break;
                default:
                    $levels[$i] = $current;
                    if (null !== $override) {
                        $types[$i] = $override;
                    }
            }
        }

        // X10: the isolating run sequences, the level runs of the characters kept, an isolate initiator joined to its PDI
        $kept = array_values(array_filter(range(0, $count - 1), static fn (int $i) => !isset($removed[$i])));
        $runs = [];
        foreach ($kept as $i) {
            if ($runs && $levels[end($runs)[array_key_last(end($runs))]] === $levels[$i]) {
                $runs[array_key_last($runs)][] = $i;
            } else {
                $runs[] = [$i];
            }
        }
        $pdis = array_flip($matchingPdi);
        $runOf = [];
        foreach ($runs as $r => $run) {
            $runOf[$run[0]] = $r;
        }
        $sequences = [];
        foreach ($runs as $run) {
            // A run starting with the PDI of an isolate initiator continues the sequence of its initiator
            if ('PDI' === $original[$run[0]] && isset($pdis[$run[0]])) {
                continue;
            }
            $sequence = $run;
            while (\in_array($original[$last = end($sequence)], ['LRI', 'RLI', 'FSI'], true) && isset($matchingPdi[$last], $runOf[$matchingPdi[$last]])) {
                array_push($sequence, ...$runs[$runOf[$matchingPdi[$last]]]);
            }
            $sequences[] = $sequence;
        }

        $position = array_flip($kept);
        foreach ($sequences as $sequence) {
            $level = $levels[$sequence[0]];
            $first = $position[$sequence[0]];
            $before = $first > 0 ? $levels[$kept[$first - 1]] : $paragraphLevel;
            $last = end($sequence);
            $after = !\in_array($original[$last], ['LRI', 'RLI', 'FSI'], true) && ($position[$last] + 1) < \count($kept) ? $levels[$kept[$position[$last] + 1]] : $paragraphLevel;
            $sos = 1 === max($level, $before) % 2 ? 'R' : 'L';
            $eos = 1 === max($level, $after) % 2 ? 'R' : 'L';
            self::resolveSequence($types, $original, $sequence, $sos, $eos, $level, $characters);
        }

        // I1-I2: the implicit levels
        foreach ($kept as $i) {
            $type = $types[$i];
            if (0 === $levels[$i] % 2) {
                $levels[$i] += 'R' === $type ? 1 : ('AN' === $type || 'EN' === $type ? 2 : 0);
            } elseif ('L' === $type || 'EN' === $type || 'AN' === $type) {
                ++$levels[$i];
            }
        }

        // L1: the separators, and the white space before them and at the end of the line, at the paragraph level
        $trailing = true;
        for ($i = $count - 1; $i >= 0; --$i) {
            $type = $original[$i];
            if ('S' === $type || 'B' === $type) {
                $levels[$i] = $paragraphLevel;
                $trailing = true;
            } elseif ($trailing && \in_array($type, ['WS', 'FSI', 'LRI', 'RLI', 'PDI', 'BN', 'LRE', 'RLE', 'LRO', 'RLO', 'PDF'], true)) {
                $levels[$i] = $paragraphLevel;
            } else {
                $trailing = false;
            }
        }

        foreach ($removed as $i => $_) {
            $levels[$i] = null;
        }

        return array_values($levels);
    }

    /**
     * W1-W7, N0-N2 on an isolating run sequence.
     *
     * @param array<int, string> $types
     * @param list<string>       $original
     * @param list<int>          $sequence
     * @param list<string>       $characters
     */
    private static function resolveSequence(array &$types, array $original, array $sequence, string $sos, string $eos, int $level, array $characters): void
    {
        $count = \count($sequence);

        // W1: a non-spacing mark takes the class of what it follows, ON after an isolate initiator or a PDI
        $previous = $sos;
        foreach ($sequence as $i) {
            if ('NSM' === $types[$i]) {
                $types[$i] = \in_array($previous, ['LRI', 'RLI', 'FSI', 'PDI'], true) ? 'ON' : $previous;
            }
            $previous = $types[$i];
        }

        // W2, W3: European numbers after Arabic letters are Arabic numbers; Arabic letters are right to left
        $strong = $sos;
        foreach ($sequence as $i) {
            $type = $types[$i];
            if ('EN' === $type && 'AL' === $strong) {
                $types[$i] = 'AN';
            }
            if ('L' === $type || 'R' === $type || 'AL' === $type) {
                $strong = $type;
            }
        }
        foreach ($sequence as $i) {
            if ('AL' === $types[$i]) {
                $types[$i] = 'R';
            }
        }

        // W4: a single separator between two numbers of the same kind is one of them
        for ($k = 1; $k < $count - 1; ++$k) {
            $type = $types[$sequence[$k]];
            $before = $types[$sequence[$k - 1]];
            $after = $types[$sequence[$k + 1]];
            if ('ES' === $type && 'EN' === $before && 'EN' === $after) {
                $types[$sequence[$k]] = 'EN';
            } elseif ('CS' === $type && $before === $after && ('EN' === $before || 'AN' === $before)) {
                $types[$sequence[$k]] = $before;
            }
        }

        // W5: terminators next to a European number are one too
        for ($k = 0; $k < $count; ++$k) {
            if ('ET' !== $types[$sequence[$k]]) {
                continue;
            }
            $end = $k;
            while ($end < $count && 'ET' === $types[$sequence[$end]]) {
                ++$end;
            }
            if (($k > 0 && 'EN' === $types[$sequence[$k - 1]]) || ($end < $count && 'EN' === $types[$sequence[$end]])) {
                for ($j = $k; $j < $end; ++$j) {
                    $types[$sequence[$j]] = 'EN';
                }
            }
            $k = $end;
        }

        // W6: the other separators and terminators are neutral
        foreach ($sequence as $i) {
            if (\in_array($types[$i], ['ES', 'ET', 'CS'], true)) {
                $types[$i] = 'ON';
            }
        }

        // W7: European numbers after left-to-right text are left to right
        $strong = $sos;
        foreach ($sequence as $i) {
            $type = $types[$i];
            if ('EN' === $type && 'L' === $strong) {
                $types[$i] = 'L';
            }
            if ('L' === $type || 'R' === $type) {
                $strong = $type;
            }
        }

        $embedding = 1 === $level % 2 ? 'R' : 'L';
        $direction = static fn (string $type): ?string => match ($type) {
            'L' => 'L',
            'R', 'AN', 'EN' => 'R',
            default => null,
        };

        // N0: the pairs of brackets take the direction of what they hold, else of what is before them
        foreach (self::bracketPairs($types, $original, $sequence, $characters) as [$open, $close]) {
            $inside = null;
            for ($k = $open + 1; $k < $close; ++$k) {
                $found = $direction($types[$sequence[$k]]);
                if ($found === $embedding) {
                    $inside = $embedding;
                    break;
                }
                $inside ??= $found;
            }
            if (null === $inside) {
                continue;
            }
            if ($inside !== $embedding) {
                $context = $sos;
                for ($k = $open - 1; $k >= 0; --$k) {
                    if (null !== $found = $direction($types[$sequence[$k]])) {
                        $context = $found;
                        break;
                    }
                }
                $inside = $context === $inside ? $inside : $embedding;
            }
            foreach ([$open, $close] as $k) {
                $types[$sequence[$k]] = $inside;
                // The non-spacing marks after a bracket take its direction
                for ($j = $k + 1; $j < $count && 'NSM' === $original[$sequence[$j]]; ++$j) {
                    $types[$sequence[$j]] = $inside;
                }
            }
        }

        // N1, N2: neutrals between characters of the same direction take it, the others the embedding direction
        $neutral = static fn (string $type): bool => \in_array($type, ['B', 'S', 'WS', 'ON', 'LRI', 'RLI', 'FSI', 'PDI'], true);
        for ($k = 0; $k < $count; ++$k) {
            if (!$neutral($types[$sequence[$k]])) {
                continue;
            }
            $end = $k;
            while ($end < $count && $neutral($types[$sequence[$end]])) {
                ++$end;
            }
            $before = $k > 0 ? $direction($types[$sequence[$k - 1]]) : $sos;
            $after = $end < $count ? $direction($types[$sequence[$end]]) : $eos;
            $resolved = null !== $before && $before === $after ? $before : $embedding;
            for ($j = $k; $j < $end; ++$j) {
                $types[$sequence[$j]] = $resolved;
            }
            $k = $end;
        }
    }

    /**
     * BD16: the pairs of brackets of a sequence, by their positions in it, in the order of their opening brackets.
     *
     * @param array<int, string> $types
     * @param list<string>       $original
     * @param list<int>          $sequence
     * @param list<string>       $characters
     *
     * @return list<array{int, int}>
     */
    private static function bracketPairs(array $types, array $original, array $sequence, array $characters): array
    {
        if (!$characters) {
            return [];
        }
        $pairs = [];
        $stack = [];
        foreach ($sequence as $k => $i) {
            if ('ON' !== $types[$i] || '' === $character = mb_substr($characters[$i], 0, 1)) {
                continue;
            }
            $kind = \IntlChar::getIntPropertyValue($character, \IntlChar::PROPERTY_BIDI_PAIRED_BRACKET_TYPE);
            if (\IntlChar::BPT_OPEN === $kind) {
                if (63 === \count($stack)) {
                    break;
                }
                $stack[] = [self::canonicalBracket((string) \IntlChar::getBidiPairedBracket($character)), $k];
            } elseif (\IntlChar::BPT_CLOSE === $kind) {
                $closing = self::canonicalBracket($character);
                for ($s = \count($stack) - 1; $s >= 0; --$s) {
                    if ($stack[$s][0] === $closing) {
                        $pairs[] = [$stack[$s][1], $k];
                        array_splice($stack, $s);
                        break;
                    }
                }
            }
        }
        usort($pairs, static fn (array $a, array $b) => $a[0] <=> $b[0]);

        return $pairs;
    }

    /**
     * The angle brackets of U+2329 and U+232A are those of U+3008 and U+3009, canonically.
     */
    private static function canonicalBracket(string $bracket): string
    {
        return match ($bracket) {
            "\u{2329}" => "\u{3008}",
            "\u{232A}" => "\u{3009}",
            default => $bracket,
        };
    }

    /**
     * BD9: the PDI that closes each isolate initiator, by their positions.
     *
     * @param list<string> $types
     *
     * @return array<int, int>
     */
    private static function matchingPdis(array $types): array
    {
        $matching = [];
        $open = [];
        foreach ($types as $i => $type) {
            if ('LRI' === $type || 'RLI' === $type || 'FSI' === $type) {
                $open[] = $i;
            } elseif ('PDI' === $type && $open) {
                $matching[array_pop($open)] = $i;
            }
        }

        return $matching;
    }

    /**
     * P2: the direction of the first strong character between two positions, skipping the isolates.
     *
     * @param list<string> $types
     */
    private static function firstStrong(array $types, int $from, int $to): ?int
    {
        $depth = 0;
        for ($i = $from; $i < $to; ++$i) {
            $type = $types[$i];
            if ('LRI' === $type || 'RLI' === $type || 'FSI' === $type) {
                ++$depth;
            } elseif ('PDI' === $type) {
                if (0 === $depth) {
                    return null;
                }
                --$depth;
            } elseif (0 === $depth && 'L' === $type) {
                return 0;
            } elseif (0 === $depth && ('R' === $type || 'AL' === $type)) {
                return 1;
            }
        }

        return null;
    }

    private static function classOf(string $grapheme): string
    {
        $codePoint = mb_ord($grapheme) ?: 0;

        return self::$classes[$codePoint] ??= self::CLASSES[\IntlChar::charDirection($codePoint)] ?? 'L';
    }
}
