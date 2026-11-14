<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Css;

/**
 * The counter styles of a style sheet: the predefined ones and those of @counter-style rules, like CSS Counter Styles.
 *
 * A style turns a counter value into its representation, by its system and
 * symbols, and a list item marker adds its prefix and suffix around it. A
 * value out of its range, or that it cannot represent, takes its fallback
 * style, decimal at last.
 *
 * @experimental
 *
 * @internal
 */
final class CounterStyles
{
    /** The styles a @counter-style rule cannot redefine */
    private const RESERVED = ['decimal', 'disc', 'square', 'circle', 'disclosure-open', 'disclosure-closed'];
    /** The longest representation a symbolic style repeats its symbol to, or pads to */
    private const MAX_LENGTH = 60;
    /** How many styles a style can extend or fall back to before decimal is taken */
    private const MAX_DEPTH = 16;
    /**
     * @var array<string, array<string, mixed>> The descriptors of each style, by name: the predefined ones first
     */
    private array $styles = [];
    /** @var array<string, array<string, mixed>> The resolved styles, extends applied */
    private array $resolved = [];

    public function __construct()
    {
        $roman = [[1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'], [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'], [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I']];
        $this->styles = ['decimal' => ['system' => 'numeric', 'symbols' => str_split('0123456789')], 'decimal-leading-zero' => ['system' => 'extends', 'extends' => 'decimal', 'pad' => [2, '0']], 'lower-alpha' => ['system' => 'alphabetic', 'symbols' => range('a', 'z')], 'lower-latin' => ['system' => 'alphabetic', 'symbols' => range('a', 'z')], 'upper-alpha' => ['system' => 'alphabetic', 'symbols' => range('A', 'Z')], 'upper-latin' => ['system' => 'alphabetic', 'symbols' => range('A', 'Z')], 'lower-greek' => ['system' => 'alphabetic', 'symbols' => mb_str_split('αβγδεζηθικλμνξοπρστυφχψω')], 'lower-roman' => ['system' => 'additive', 'additive' => array_map(static fn (array $pair) => [$pair[0], strtolower($pair[1])], $roman), 'range' => [[1, 3999]]], 'upper-roman' => ['system' => 'additive', 'additive' => $roman, 'range' => [[1, 3999]]], 'cjk-decimal' => ['system' => 'numeric', 'symbols' => mb_str_split('〇一二三四五六七八九'), 'suffix' => '、'], 'disc' => ['system' => 'cyclic', 'symbols' => ['•'], 'suffix' => ' '], 'circle' => ['system' => 'cyclic', 'symbols' => ['◦'], 'suffix' => ' '], 'square' => ['system' => 'cyclic', 'symbols' => ['▪'], 'suffix' => ' '], 'disclosure-open' => ['system' => 'cyclic', 'symbols' => ['▾'], 'suffix' => ' '], 'disclosure-closed' => ['system' => 'cyclic', 'symbols' => ['▸'], 'suffix' => ' ']];
        // The other predefined styles of CSS Counter Styles: the digits of scripts...
        foreach (['arabic-indic' => '٠١٢٣٤٥٦٧٨٩', 'bengali' => '০১২৩৪৫৬৭৮৯', 'cambodian' => '០១២៣៤៥៦៧៨៩', 'khmer' => '០១២៣៤៥៦៧៨៩', 'devanagari' => '०१२३४५६७८९', 'gujarati' => '૦૧૨૩૪૫૬૭૮૯', 'gurmukhi' => '੦੧੨੩੪੫੬੭੮੯', 'kannada' => '೦೧೨೩೪೫೬೭೮೯', 'lao' => '໐໑໒໓໔໕໖໗໘໙', 'malayalam' => '൦൧൨൩൪൫൬൭൮൯', 'mongolian' => '᠐᠑᠒᠓᠔᠕᠖᠗᠘᠙', 'myanmar' => '၀၁၂၃၄၅၆၇၈၉', 'oriya' => '୦୧୨୩୪୫୬୭୮୯', 'persian' => '۰۱۲۳۴۵۶۷۸۹', 'tamil' => '௦௧௨௩௪௫௬௭௮௯', 'telugu' => '౦౧౨౩౪౫౬౭౮౯', 'thai' => '๐๑๒๓๔๕๖๗๘๙', 'tibetan' => '༠༡༢༣༤༥༦༧༨༩'] as $name => $digits) {
            $this->styles[$name] = ['system' => 'numeric', 'symbols' => mb_str_split($digits)];
        }
        // ...the kana, in their order and in that of the iroha poem...
        foreach (['hiragana' => 'あいうえおかきくけこさしすせそたちつてとなにぬねのはひふへほまみむめもやゆよらりるれろわゐゑをん', 'hiragana-iroha' => 'いろはにほへとちりぬるをわかよたれそつねならむうゐのおくやまけふこえてあさきゆめみしゑひもせす', 'katakana' => 'アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヰヱヲン', 'katakana-iroha' => 'イロハニホヘトチリヌルヲワカヨタレソツネナラムウヰノオクヤマケフコエテアサキユメミシヱヒモセス', 'cjk-earthly-branch' => '子丑寅卯辰巳午未申酉戌亥', 'cjk-heavenly-stem' => '甲乙丙丁戊己庚辛壬癸'] as $name => $letters) {
            $this->styles[$name] = ['system' => 'alphabetic', 'symbols' => mb_str_split($letters), 'suffix' => '、'] + (str_starts_with($name, 'cjk-') ? ['fallback' => 'cjk-decimal'] : []);
        }
        // ...letters that are numbers, added up...
        $armenian = self::places('ԱԲԳԴԵԶԷԸԹ', 'ԺԻԼԽԾԿՀՁՂ', 'ՃՄՅՆՇՈՉՊՋ', 'ՌՍՎՏՐՑՒՓՔ');
        $this->styles['upper-armenian'] = $this->styles['armenian'] = ['system' => 'additive', 'additive' => $armenian, 'range' => [[1, 9999]]];
        $this->styles['lower-armenian'] = ['system' => 'additive', 'additive' => array_map(static fn (array $pair) => [$pair[0], mb_strtolower($pair[1])], $armenian), 'range' => [[1, 9999]]];
        $this->styles['georgian'] = ['system' => 'additive', 'additive' => [[10000, 'ჵ'], ...self::places('აბგდევზჱთ', 'იკლმნჲოპჟ', 'რსტჳფქღყშ', 'ჩცძწჭხჴჯჰ')], 'range' => [[1, 19999]]];
        // 15 and 16 are not written like the name of God, and 17 to 19 follow them, before 10
        $hebrew = self::places('אבגדהוזחט', 'יכלמנסעפצ', 'קרשת');
        array_splice($hebrew, \count($hebrew) - 10, 0, [[19, 'יט'], [18, 'יח'], [17, 'יז'], [16, 'טז'], [15, 'טו']]);
        $thousands = array_map(static fn (array $pair) => [$pair[0] * 1000, $pair[1].'׳'], self::places('אבגדהוזחט'));
        $this->styles['hebrew'] = ['system' => 'additive', 'additive' => [[10000, 'י׳'], ...$thousands, ...$hebrew], 'range' => [[1, 10999]]];
        // ...and the long forms of East Asian numbers, from -9999 to 9999
        $east = static fn (string $digits, string $units, string $zero, array $negative, string $suffix, bool $one) => ['system' => 'additive', 'additive' => [...self::spelled($digits, $units, $one), [0, $zero]], 'range' => [[-9999, 9999]], 'negative' => $negative, 'suffix' => $suffix, 'fallback' => 'cjk-decimal'];
        $this->styles['japanese-informal'] = $east('一二三四五六七八九', '千百十', '〇', ['マイナス', ''], '、', false);
        $this->styles['japanese-formal'] = $east('壱弐参四伍六七八九', '阡百拾', '零', ['マイナス', ''], '、', true);
        $this->styles['korean-hangul-formal'] = $east('일이삼사오육칠팔구', '천백십', '영', ['마이너스 ', ''], ', ', true);
        $this->styles['korean-hanja-informal'] = $east('一二三四五六七八九', '千百十', '零', ['마이너스 ', ''], ', ', false);
        $this->styles['korean-hanja-formal'] = $east('壹貳參四五六七八九', '仟百拾', '零', ['마이너스 ', ''], ', ', true);
        $this->styles['ethiopic-numeric'] = ['system' => 'ethiopic', 'range' => [[1, null]], 'suffix' => '/ '];
        foreach (['simp-chinese-informal' => ['零一二三四五六七八九', '千百十', '负', false], 'simp-chinese-formal' => ['零壹贰叁肆伍陆柒捌玖', '仟佰拾', '负', true], 'trad-chinese-informal' => ['零一二三四五六七八九', '千百十', '負', false], 'trad-chinese-formal' => ['零壹貳參肆伍陸柒捌玖', '仟佰拾', '負', true]] as $name => [$digits, $units, $minus, $formal]) {
            $this->styles[$name] = ['system' => 'chinese', 'symbols' => mb_str_split($digits), 'units' => mb_str_split($units), 'formal' => $formal, 'range' => [[-9999, 9999]], 'negative' => [$minus, ''], 'suffix' => '、', 'fallback' => 'cjk-decimal'];
        }
    }

    /**
     * Reads the descriptors of a @counter-style rule.
     *
     * @param array<string, string> $descriptors The descriptors, by lowercase name
     *
     * @return array<string, mixed>|null The style, null when it is invalid
     */
    public static function parse(string $name, array $descriptors): ?array
    {
        if (!preg_match('/^-?[a-z_][\w-]*$/i', $name) || \in_array(strtolower($name), ['none', 'inherit', 'initial', 'unset', 'revert', 'revert-layer', 'default'], true) || \in_array(strtolower($name), self::RESERVED, true)) {
            return null;
        }
        $style = [];
        $system = Value::split(strtolower(trim($descriptors['system'] ?? 'symbolic')));
        switch ($system[0] ?? '') {
            case 'cyclic':
            case 'numeric':
            case 'alphabetic':
            case 'symbolic':
            case 'additive':
                if (1 !== \count($system)) {
                    return null;
                }
                $style['system'] = $system[0];
                break;
            case 'fixed':
                if (\count($system) > 2 || isset($system[1]) && !preg_match('/^[+-]?\d{1,9}$/', $system[1])) {
                    return null;
                }
                $style['system'] = 'fixed';
                $style['first'] = (int) ($system[1] ?? 1);
                break;
            case 'extends':
                if (2 !== \count($system) || !preg_match('/^-?[a-z_][\w-]*$/', $system[1]) || 'none' === $system[1]) {
                    return null;
                }
                $style['system'] = 'extends';
                $style['extends'] = $system[1];
                break;
            default:
                return null;
        }
        if (isset($descriptors['symbols'])) {
            if (null === $symbols = self::symbols($descriptors['symbols'])) {
                return null;
            }
            $style['symbols'] = $symbols;
        }
        if (isset($descriptors['additive-symbols'])) {
            $additive = [];
            foreach (Selector::splitTopLevel($descriptors['additive-symbols']) as $tuple) {
                // A weight and a symbol, in either order
                $parts = self::symbols(preg_replace('/(?<![\w.-])([+-]?\d+)(?![\w.-])/', '', $tuple) ?? '');
                if (!preg_match('/(?<![\w.-])(\d+)(?![\w.-])/', $tuple, $weight) || null === $parts || 1 !== \count($parts)) {
                    return null;
                }
                // Weights go down
                if ($additive && $additive[array_key_last($additive)][0] <= (int) $weight[1]) {
                    return null;
                }
                $additive[] = [(int) $weight[1], $parts[0]];
            }
            $style['additive'] = $additive;
        }
        // The symbols each system needs, none with extends
        $valid = match ($style['system']) {
            'cyclic', 'fixed', 'symbolic' => \count($style['symbols'] ?? []) >= 1 && !isset($style['additive']),
            'alphabetic', 'numeric' => \count($style['symbols'] ?? []) >= 2 && !isset($style['additive']),
            'additive' => [] !== ($style['additive'] ?? []) && !isset($style['symbols']),
            default => !isset($style['symbols']) && !isset($style['additive']),
        };
        if (!$valid) {
            return null;
        }
        foreach (['prefix', 'suffix'] as $descriptor) {
            if (isset($descriptors[$descriptor])) {
                if (null === $symbol = self::symbols($descriptors[$descriptor])) {
                    return null;
                }
                if (1 !== \count($symbol)) {
                    return null;
                }
                $style[$descriptor] = $symbol[0];
            }
        }
        if (isset($descriptors['negative'])) {
            $negative = self::symbols($descriptors['negative']);
            if (null === $negative || \count($negative) > 2) {
                return null;
            }
            $style['negative'] = [$negative[0], $negative[1] ?? ''];
        }
        if (isset($descriptors['pad'])) {
            if (!preg_match('/^\s*(\d{1,4})\s+(.+)$/s', $descriptors['pad'], $m) && !preg_match('/^\s*(.+?)\s+(\d{1,4})\s*$/s', $descriptors['pad'], $m)) {
                return null;
            }
            [$length, $symbol] = ctype_digit(trim($m[1])) ? [(int) $m[1], $m[2]] : [(int) $m[2], $m[1]];
            $symbol = self::symbols($symbol);
            if (null === $symbol || 1 !== \count($symbol)) {
                return null;
            }
            $style['pad'] = [min(self::MAX_LENGTH, $length), $symbol[0]];
        }
        if (isset($descriptors['range'])) {
            if ('auto' !== strtolower(trim($descriptors['range']))) {
                $ranges = [];
                foreach (Selector::splitTopLevel($descriptors['range']) as $range) {
                    $bounds = Value::split(strtolower($range));
                    if (2 !== \count($bounds)) {
                        return null;
                    }
                    $bound = static fn (string $bound, string $infinite): int|false|null => $infinite === $bound ? null : (preg_match('/^[+-]?\d{1,9}$/', $bound) ? (int) $bound : false);
                    $low = $bound($bounds[0], 'infinite');
                    $high = $bound($bounds[1], 'infinite');
                    if (false === $low || false === $high || null !== $low && null !== $high && $low > $high) {
                        return null;
                    }
                    $ranges[] = [$low, $high];
                }
                $style['range'] = $ranges;
            }
        }
        if (isset($descriptors['fallback'])) {
            $fallback = strtolower(trim($descriptors['fallback']));
            if (!preg_match('/^-?[a-z_][\w-]*$/', $fallback) || 'none' === $fallback) {
                return null;
            }
            $style['fallback'] = $fallback;
        }

        return $style;
    }

    /**
     * Adds the styles of @counter-style rules: a later style of a name replaces the earlier one.
     *
     * @param array<string, array<string, mixed>> $styles
     */
    public function add(array $styles): void
    {
        if (!$styles) {
            return;
        }
        foreach ($styles as $name => $style) {
            if (!\in_array($name, self::RESERVED, true)) {
                $this->styles[$name] = $style;
            }
        }
        $this->resolved = [];
    }

    public function has(string $name): bool
    {
        return isset($this->styles[strtolower($name)]);
    }

    /**
     * The representation of a value, like counter(name, style): without the prefix and suffix; an unknown style is decimal.
     */
    public function format(int $value, string $name): string
    {
        return $this->represent($value, strtolower($name), 0);
    }

    /**
     * The marker of a list item: its prefix, the representation of its ordinal and its suffix.
     */
    public function marker(int $value, string $name): string
    {
        $style = $this->resolve(strtolower($name));

        return $style['prefix'].$this->format($value, $name).$style['suffix'];
    }

    /**
     * Additive symbols of letters for 1 to 9, then 10 to 90, 100 to 900...: the largest first.
     *
     * @return list<array{int, string}>
     */
    private static function places(string ...$places): array
    {
        $additive = [];
        foreach ($places as $place => $letters) {
            foreach (mb_str_split($letters) as $i => $letter) {
                $additive[] = [($i + 1) * 10 ** $place, $letter];
            }
        }
        usort($additive, static fn (array $a, array $b) => $b[0] <=> $a[0]);

        return $additive;
    }

    /**
     * Additive symbols of numbers spelled with digits and the units of thousands, hundreds and tens, like 九千; the
     * digit one written before a unit in formal styles only.
     *
     * @return list<array{int, string}>
     */
    private static function spelled(string $digits, string $units, bool $one): array
    {
        $additive = [];
        foreach (mb_str_split($units) as $place => $unit) {
            foreach (array_reverse(mb_str_split($digits), true) as $i => $digit) {
                $additive[] = [($i + 1) * 10 ** (3 - $place), (0 === $i && !$one ? '' : $digit).$unit];
            }
        }
        foreach (array_reverse(mb_str_split($digits), true) as $i => $digit) {
            $additive[] = [$i + 1, $digit];
        }

        return $additive;
    }

    /**
     * @return array{system: string, first: int, symbols: list<string>, additive: list<array{int, string}>, negative: array{string, string}, prefix: string, suffix: string, range: list<array{int|null, int|null}>|null, pad: array{int, string}|null, fallback: string}
     */
    private function resolve(string $name, int $depth = 0): array
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }
        // An unknown style is decimal, and so is one extending in a loop
        $style = $this->styles[$name] ?? $this->styles['decimal'];
        if ('extends' === $style['system']) {
            $base = $depth < self::MAX_DEPTH && isset($this->styles[$style['extends']]) ? $this->resolve($style['extends'], $depth + 1) : $this->resolve('decimal', self::MAX_DEPTH);
            unset($style['system'], $style['extends']);
            // The algorithm of the base, with the other descriptors of the style
            $style = ['system' => $base['system'], 'first' => $base['first'], 'symbols' => $base['symbols'], 'additive' => $base['additive']] + $style + $base;
        }

        return $this->resolved[$name] = $style + ['first' => 1, 'symbols' => [], 'additive' => [], 'negative' => ['-', ''], 'prefix' => '', 'suffix' => '. ', 'range' => null, 'pad' => null, 'fallback' => 'decimal'];
    }

    private function represent(int $value, string $name, int $depth): string
    {
        $style = $this->resolve($name);
        $representation = $this->inRange($value, $style) ? $this->generate(abs($value), $value < 0, $style) : null;
        if (null === $representation) {
            // Out of the range, or not representable: the fallback, decimal at last
            return $depth < self::MAX_DEPTH && 'decimal' !== $name ? $this->represent($value, $style['fallback'], $depth + 1) : (string) $value;
        }

        return $representation;
    }

    /**
     * @param array<string, mixed> $style
     */
    private function inRange(int $value, array $style): bool
    {
        if (null === $style['range']) {
            // The range of the system: auto
            return match ($style['system']) {
                'alphabetic', 'symbolic' => $value >= 1,
                'additive' => $value >= 0,
                default => true,
            };
        }
        foreach ($style['range'] as [$low, $high]) {
            if ((null === $low || $value >= $low) && (null === $high || $value <= $high)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $style
     */
    private function generate(int $value, bool $negative, array $style): ?string
    {
        $symbols = $style['symbols'];
        $count = \count($symbols);
        // The systems that do not use negative: the value as it is
        $signed = $negative && !\in_array($style['system'], ['symbolic', 'alphabetic', 'numeric', 'additive', 'chinese'], true);
        $original = $signed ? -$value : $value;
        $representation = match ($style['system']) {
            'cyclic' => $count ? $symbols[(($original - 1) % $count + $count) % $count] : null,
            'fixed' => $original >= $style['first'] && $original - $style['first'] < $count ? $symbols[$original - $style['first']] : null,
            'symbolic' => $count && $value >= 1 && intdiv($value - 1, $count) < self::MAX_LENGTH ? str_repeat($symbols[($value - 1) % $count], intdiv($value - 1, $count) + 1) : null,
            'alphabetic' => $count >= 2 && $value >= 1 ? self::alphabetic($value, $symbols) : null,
            'numeric' => $count >= 2 ? self::numeric($value, $symbols) : null,
            'additive' => self::additive($value, $style['additive']),
            'chinese' => self::chinese($value, $style['symbols'], $style['units'], $style['formal']),
            'ethiopic' => self::ethiopic($value),
            default => null,
        };
        if (null === $representation) {
            return null;
        }
        $uses = $negative && !$signed;
        [$before, $after] = $uses ? $style['negative'] : ['', ''];
        if (null !== $style['pad']) {
            [$length, $symbol] = $style['pad'];
            $missing = $length - mb_strlen($before.$representation.$after);
            if ($missing > 0) {
                $representation = str_repeat($symbol, $missing).$representation;
            }
        }

        return $before.$representation.$after;
    }

    /**
     * A number from 0 to 9999 in Chinese longhand, like CSS Counter Styles: the digits with the units of their place,
     * one zero for the zeros between them, none at the end, and 十 alone for one ten in informal styles.
     *
     * @param list<string> $digits From zero to nine
     * @param list<string> $units  Of the thousands, the hundreds and the tens
     */
    private static function chinese(int $value, array $digits, array $units, bool $formal): ?string
    {
        if ($value > 9999) {
            return null;
        }
        if (0 === $value) {
            return $digits[0];
        }
        $result = '';
        $zero = false;
        foreach ([1000, 100, 10, 1] as $place => $size) {
            $digit = intdiv($value, $size) % 10;
            if (0 === $digit) {
                $zero = '' !== $result;
                continue;
            }
            if ($zero) {
                $result .= $digits[0];
                $zero = false;
            }
            $result .= (!$formal && 10 === $size && 1 === $digit && $value < 20 ? '' : $digits[$digit]).($units[$place] ?? '');
        }

        return $result;
    }

    /**
     * A number in Ethiopic numerals, like CSS Counter Styles: its pairs of digits from the right, those of odd places
     * followed by ፻ and the others but the last by ፼, a pair of 1 written by its separator alone where it would multiply.
     */
    private static function ethiopic(int $value): string
    {
        if (1 === $value) {
            return '፩';
        }
        $groups = array_reverse(str_split(strrev((string) $value), 2));
        $last = \count($groups) - 1;
        $result = '';
        foreach ($groups as $i => $digits) {
            $group = (int) strrev($digits);
            $index = $last - $i;
            $written = '';
            if (0 !== $group && !(1 === $group && (0 === $i || 1 === $index % 2))) {
                $written = ($group >= 10 ? mb_chr(0x1371 + intdiv($group, 10)) : '').(0 !== $group % 10 ? mb_chr(0x1368 + $group % 10) : '');
            }
            if (1 === $index % 2 && 0 !== $group) {
                $written .= '፻';
            } elseif (0 === $index % 2 && 0 !== $index) {
                $written .= '፼';
            }
            $result .= $written;
        }

        return $result;
    }

    /**
     * @param list<string> $symbols
     */
    private static function alphabetic(int $value, array $symbols): string
    {
        $count = \count($symbols);
        $result = '';
        while ($value > 0) {
            --$value;
            $result = $symbols[$value % $count].$result;
            $value = intdiv($value, $count);
        }

        return $result;
    }

    /**
     * @param list<string> $symbols
     */
    private static function numeric(int $value, array $symbols): string
    {
        $count = \count($symbols);
        if (0 === $value) {
            return $symbols[0];
        }
        $result = '';
        while ($value > 0) {
            $result = $symbols[$value % $count].$result;
            $value = intdiv($value, $count);
        }

        return $result;
    }

    /**
     * @param list<array{int, string}> $symbols Weights going down
     */
    private static function additive(int $value, array $symbols): ?string
    {
        if (0 === $value) {
            foreach ($symbols as [$weight, $symbol]) {
                if (0 === $weight) {
                    return $symbol;
                }
            }

            return null;
        }
        $result = '';
        foreach ($symbols as [$weight, $symbol]) {
            if (0 === $weight) {
                continue;
            }
            $times = intdiv($value, $weight);
            if ($times > 0) {
                if (mb_strlen($result) + $times * mb_strlen($symbol) > self::MAX_LENGTH * 4) {
                    return null;
                }
                $result .= str_repeat($symbol, $times);
                $value -= $times * $weight;
            }
            if (0 === $value) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Reads symbols: strings or identifiers, separated by whitespace.
     *
     * @return list<string>|null
     */
    private static function symbols(string $value): ?array
    {
        $value = trim($value);
        if ('' === $value || !preg_match_all('/\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^\s"\']+)/sA', $value, $m) || implode('', $m[0]) !== $value) {
            return null;
        }
        $symbols = [];
        foreach ($m[1] as $token) {
            $quoted = '"' === $token[0] || '\'' === $token[0];
            if (!$quoted && !preg_match('/^-?[a-z_\x80-\x{10FFFF}][\w\x80-\x{10FFFF}-]*$/iu', $token)) {
                // Images and numbers are not symbols
                return null;
            }
            $symbols[] = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?|\\\\(.)/s', static fn (array $e) => '' !== $e[1] ? mb_chr(min(\intval($e[1], 16), 0x10FFFF)) ?: '�' : $e[2], $quoted ? substr($token, 1, -1) : $token) ?? '';
        }

        return $symbols;
    }
}
