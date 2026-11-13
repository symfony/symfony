<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\MediaContext;

/**
 * A CSS animation of an element: its keyframes name, timing and repetition.
 *
 * @internal
 */
final class Animation
{
    /** How many steps a smooth animation is cut into, which bounds the styles it computes */
    private const STEPS = 32;

    /** The control points of the named easing functions */
    private const BEZIERS = [
        'ease' => [0.25, 0.1, 0.25, 1.0],
        'ease-in' => [0.42, 0.0, 1.0, 1.0],
        'ease-out' => [0.0, 0.0, 0.58, 1.0],
        'ease-in-out' => [0.42, 0.0, 0.58, 1.0],
    ];

    /** The initial values of properties often animated, for a missing first or last keyframe */
    private const INITIAL = [
        'opacity' => '1', 'letter-spacing' => '0', 'text-indent' => '0',
        'margin-left' => '0', 'margin-right' => '0', 'margin-top' => '0', 'margin-bottom' => '0',
        'padding-left' => '0', 'padding-right' => '0', 'padding-top' => '0', 'padding-bottom' => '0',
        'background-color' => 'transparent', 'top' => '0', 'left' => '0', 'right' => '0', 'bottom' => '0',
    ];

    /**
     * @param string                                                                                              $name        The @keyframes name
     * @param float                                                                                               $duration    In seconds
     * @param float                                                                                               $delay       In seconds
     * @param float                                                                                               $iterations  How many times it plays, INF for ever
     * @param array{0: string, 1: int, 2: 'start'|'end'|'none'|'both', 3?: list<float>|list<array{float, float}>} $timing      The function (linear, ease, ease-in, ease-out, ease-in-out, cubic-bezier, steps), its steps, where they jump (start, end, none or both), the control points of a cubic-bezier() or the points of a linear()
     * @param string                                                                                              $timeline    What drives it, like animation-timeline: auto for time, scroll(...), view(...) or the name of a scroll or view timeline
     * @param array{array{string, float}, array{string, float}}                                                   $range       Where it starts and ends on a view timeline, like animation-range: a range (normal, cover, contain, entry, exit) and a percentage of it
     * @param 'replace'|'add'|'accumulate'                                                                        $composition How its keyframes combine with the element's own values, like animation-composition
     */
    private function __construct(
        public readonly string $name,
        public readonly float $duration,
        public readonly float $delay,
        public readonly float $iterations,
        public readonly string $direction,
        public readonly string $fill,
        public readonly array $timing,
        public readonly bool $paused,
        public readonly string $timeline = 'auto',
        public readonly array $range = [['normal', 0.0], ['normal', 100.0]],
        public readonly string $composition = 'replace',
    ) {
    }

    /**
     * Whether it is driven by scrolling, like animation-timeline: scroll(), rather than by time.
     */
    public function isScrollDriven(): bool
    {
        return 'auto' !== $this->timeline && 'none' !== $this->timeline;
    }

    /**
     * Where it is at a point of its scroll timeline: 0 where the range starts, 1 where it ends; null when it does not
     * apply there, before or after it without fill.
     */
    public function progressAt(float $fraction): ?float
    {
        if ($fraction < 0.0) {
            return \in_array($this->fill, ['backwards', 'both'], true) ? $this->directed(0.0, 0) : null;
        }
        $iterations = is_finite($this->iterations) && $this->iterations > 0 ? $this->iterations : 1.0;
        // The end of a scroll timeline is in it, like scrolled to the bottom
        if ($fraction > 1.0) {
            if (!\in_array($this->fill, ['forwards', 'both'], true)) {
                return null;
            }
            $fraction = 1.0;
        }
        $cycles = $fraction * $iterations;
        if ($cycles >= $iterations) {
            $whole = (int) floor($iterations);
            $part = $iterations - $whole;

            return $this->directed(0.0 === $part ? 1.0 : $part, 0.0 === $part ? max(0, $whole - 1) : $whole);
        }

        return $this->directed($cycles - floor($cycles), (int) floor($cycles));
    }

    /**
     * The range of animation-range-start or animation-range-end: a range name and a percentage of it.
     *
     * @return array{string, float}|null
     */
    public static function rangeEdge(string $value, float $default): ?array
    {
        $parts = Value::split(strtolower(trim($value)));
        $name = 'normal';
        $percent = $default;
        if (isset($parts[0]) && \in_array($parts[0], ['normal', 'cover', 'contain', 'entry', 'exit', 'entry-crossing', 'exit-crossing'], true)) {
            $name = array_shift($parts);
        }
        if (isset($parts[0]) && preg_match('/^(-?\d*\.?\d+)%$/', $parts[0], $m)) {
            $percent = (float) $m[1];
            array_shift($parts);
        }

        return [] === $parts && ('normal' !== $name || [] !== Value::split($value)) ? [$name, $percent] : null;
    }

    /**
     * The animations of the computed declarations, in order: one per name of animation-name, or per layer of the shorthand; a later one wins over an earlier one on the properties both animate.
     *
     * @param array<string, string> $computed
     *
     * @return array<string, self> Keyed by name and occurrence, like "pulse#0", so that an animation keeps going when the list changes around it
     */
    public static function listFromDeclarations(array $computed): array
    {
        $layers = [];
        foreach (Selector::splitTopLevel($computed['animation'] ?? '') as $layer) {
            $layers[] = self::layer($layer);
        }

        $list = static fn (string $property): array => isset($computed[$property]) ? array_map(static fn (string $v) => 'animation-name' === $property || ('animation-timeline' === $property && str_starts_with(trim($v), '--')) ? trim($v) : strtolower(trim($v)), Selector::splitTopLevel($computed[$property])) : [];
        // animation-name sets how many animations there are; the other lists repeat or are cut to it, like CSS
        if ($names = $list('animation-name')) {
            $layers = array_map(static fn (string $name, int $i) => ['name' => $name] + ($layers[$i] ?? self::layer('')), $names, array_keys($names));
        }
        if (!$layers) {
            return [];
        }
        foreach ([
            'animation-duration' => static fn (string $v) => ['duration' => self::time($v)],
            'animation-delay' => static fn (string $v) => ['delay' => self::time($v)],
            'animation-iteration-count' => static fn (string $v) => ['iterations' => 'infinite' === $v ? \INF : max(0.0, (float) $v)],
            'animation-direction' => static fn (string $v) => ['direction' => $v],
            'animation-fill-mode' => static fn (string $v) => ['fill' => $v],
            'animation-timing-function' => static fn (string $v) => null !== ($t = self::timing($v)) ? ['timing' => $t] : [],
            'animation-play-state' => static fn (string $v) => ['paused' => 'paused' === $v],
            'animation-timeline' => static fn (string $v) => ['timeline' => $v],
            'animation-range' => static fn (string $v) => null !== ($range = self::range($v)) ? ['range' => $range] : [],
            'animation-range-start' => static fn (string $v) => null !== ($edge = self::rangeEdge($v, 0.0)) ? ['rangeStart' => $edge] : [],
            'animation-range-end' => static fn (string $v) => null !== ($edge = self::rangeEdge($v, 100.0)) ? ['rangeEnd' => $edge] : [],
            'animation-composition' => static fn (string $v) => \in_array($v, ['replace', 'add', 'accumulate'], true) ? ['composition' => $v] : [],
        ] as $property => $read) {
            if ($values = $list($property)) {
                foreach ($layers as $i => $layer) {
                    $layers[$i] = $read($values[$i % \count($values)]) + $layer;
                }
            }
        }

        $animations = [];
        $occurrences = [];
        foreach (\array_slice($layers, 0, 16) as $layer) {
            ['name' => $name, 'duration' => $duration, 'delay' => $delay] = $layer;
            $timeline = $layer['timeline'] ?? 'auto';
            // A scroll timeline has no duration: it goes from the start of its range to its end
            $scrolled = 'auto' !== $timeline && 'none' !== $timeline;
            if (null === $name || 'none' === $name || (!$scrolled && (null === $duration || $duration <= 0.0))) {
                continue;
            }
            $range = $layer['range'] ?? [['normal', 0.0], ['normal', 100.0]];
            $range = [$layer['rangeStart'] ?? $range[0], $layer['rangeEnd'] ?? $range[1]];
            $occurrence = $occurrences[$name] = isset($occurrences[$name]) ? $occurrences[$name] + 1 : 0;
            $animations[$name.'#'.$occurrence] = new self($name, $scrolled ? 1.0 : min($duration, 86_400.0), $scrolled ? 0.0 : max(-86_400.0, min(86_400.0, $delay ?? 0.0)), $layer['iterations'], $layer['direction'], $layer['fill'], $layer['timing'], $layer['paused'], $timeline, $range, $layer['composition'] ?? 'replace');
        }

        return $animations;
    }

    /**
     * The first animation of the computed declarations, null without one.
     *
     * @param array<string, string> $computed
     */
    public static function fromDeclarations(array $computed): ?self
    {
        return array_values(self::listFromDeclarations($computed))[0] ?? null;
    }

    /**
     * Reads a layer of the animation shorthand.
     *
     * @return array{name: string|null, duration: float|null, delay: float|null, iterations: float, direction: string, fill: string, timing: array{0: string, 1: int, 2: 'start'|'end'|'none'|'both', 3?: list<float>|list<array{float, float}>}, paused: bool}
     */
    /**
     * The start and end of animation-range: one range for both, like entry, or two edges, like entry 10% exit 90%.
     *
     * @return array{array{string, float}, array{string, float}}|null
     */
    private static function range(string $value): ?array
    {
        $parts = Value::split(strtolower(trim($value)));
        $names = ['normal', 'cover', 'contain', 'entry', 'exit', 'entry-crossing', 'exit-crossing'];
        // A lone range name: the whole of it
        if (1 === \count($parts) && \in_array($parts[0], $names, true)) {
            return [[$parts[0], 0.0], [$parts[0], 100.0]];
        }
        // The start, then the end, each a name and a percentage, or one of them
        $edges = [];
        $current = null;
        foreach ($parts as $part) {
            if (\in_array($part, $names, true)) {
                if (null !== $current) {
                    $edges[] = $current;
                }
                $current = [$part, null];
            } elseif (preg_match('/^(-?\d*\.?\d+)%$/', $part, $m)) {
                $current = null === $current ? ['normal', (float) $m[1]] : (null === $current[1] ? [$current[0], (float) $m[1]] : null);
                if (null === $current) {
                    return null;
                }
                $edges[] = $current;
                $current = null;
            } else {
                return null;
            }
        }
        if (null !== $current) {
            $edges[] = $current;
        }
        if (!$edges || \count($edges) > 2) {
            return null;
        }
        $start = [$edges[0][0], $edges[0][1] ?? 0.0];
        $end = isset($edges[1]) ? [$edges[1][0], $edges[1][1] ?? 100.0] : [$edges[0][0], 100.0];

        return [$start, $end];
    }

    private static function layer(string $shorthand): array
    {
        $layer = ['name' => null, 'duration' => null, 'delay' => null, 'iterations' => 1.0, 'direction' => 'normal', 'fill' => 'none', 'timing' => ['ease', 0, 'end'], 'paused' => false];
        $times = [];
        foreach (Value::split($shorthand) as $part) {
            $name = $part;
            $part = strtolower($part);
            if (null !== $time = self::time($part)) {
                $times[] = $time;
            } elseif (null !== $timing = self::timing($part)) {
                $layer['timing'] = $timing;
            } elseif ('infinite' === $part || is_numeric($part)) {
                $layer['iterations'] = 'infinite' === $part ? \INF : max(0.0, (float) $part);
            } elseif (\in_array($part, ['normal', 'reverse', 'alternate', 'alternate-reverse'], true)) {
                $layer['direction'] = $part;
            } elseif (\in_array($part, ['forwards', 'backwards', 'both'], true)) {
                $layer['fill'] = $part;
            } elseif (\in_array($part, ['running', 'paused'], true)) {
                $layer['paused'] = 'paused' === $part;
            } elseif ('none' !== $part) {
                $layer['name'] = $name;
            }
        }
        // The first time is the duration, the second one the delay
        $layer['duration'] = $times[0] ?? ('' === trim($shorthand) ? null : 0.0);
        $layer['delay'] = $times[1] ?? 0.0;

        return $layer;
    }

    /**
     * Where the animation is after some time: from 0 to 1, null when it does not apply then.
     */
    public function progress(float $elapsed): ?float
    {
        $elapsed -= $this->delay;
        if ($elapsed < 0) {
            return \in_array($this->fill, ['backwards', 'both'], true) ? $this->directed(0.0, 0) : null;
        }
        $cycles = $elapsed / $this->duration;
        if ($cycles >= $this->iterations) {
            if (!\in_array($this->fill, ['forwards', 'both'], true)) {
                return null;
            }
            $whole = (int) floor($this->iterations);
            $fraction = $this->iterations - $whole;

            return $this->directed(0.0 === $fraction ? 1.0 : $fraction, 0.0 === $fraction ? max(0, $whole - 1) : $whole);
        }

        return $this->directed($cycles - floor($cycles), (int) floor($cycles));
    }

    /**
     * Whether it still changes after some time.
     */
    public function isRunning(float $elapsed): bool
    {
        return !$this->paused && ($elapsed - $this->delay) / $this->duration < $this->iterations;
    }

    /**
     * The declarations of the keyframes at a progress, over the element's own: a missing first or last keyframe is the
     * element's value, or the initial one.
     *
     * @param list<array{float, array<string, string>}> $keyframes
     * @param array<string, string>                     $computed
     *
     * @return array<string, string>
     */
    public function interpolate(array $keyframes, float $progress, array $computed, MediaContext $media): array
    {
        $properties = [];
        foreach ($keyframes as [, $declarations]) {
            $properties += $declarations;
        }

        $values = [];
        foreach (array_keys($properties) as $property) {
            // The keyframes that set it, the element's own value standing in for a missing first or last one
            $frames = [];
            foreach ($keyframes as [$offset, $declarations]) {
                if (isset($declarations[$property])) {
                    $frames[] = [$offset, $declarations[$property]];
                }
            }
            if (null !== $own = $computed[$property] ?? self::INITIAL[$property] ?? null) {
                // animation-composition: the keyframes set are added to the element's value, those missing are it
                if ('replace' !== $this->composition) {
                    $frames = array_map(static fn (array $frame) => [$frame[0], self::add($own, $frame[1], $media)], $frames);
                }
                if ($frames[0][0] > 0.0) {
                    array_unshift($frames, [0.0, $own]);
                }
                if ($frames[\count($frames) - 1][0] < 1.0) {
                    $frames[] = [1.0, $own];
                }
            }
            $before = $frames[0];
            $after = $frames[\count($frames) - 1];
            foreach ($frames as $frame) {
                if ($frame[0] <= $progress) {
                    $before = $frame;
                }
                if ($frame[0] >= $progress) {
                    $after = $frame;
                    break;
                }
            }
            // Like CSS, the timing function applies between two keyframes
            $span = $after[0] - $before[0];
            $values[$property] = self::between($before[1], $after[1], $span > 0 ? self::ease($this->timing, ($progress - $before[0]) / $span) : 0.0, $media);
        }

        return $values;
    }

    /**
     * The value between two others: colors and numbers in between, also within lists and functions of the same shape
     * (like 1ch 2ch, translate(0, 1lh) or a shadow with its color), lengths of other units through calc(); others halfway.
     */
    public static function between(string $from, string $to, float $t, MediaContext $media): string
    {
        if (0.0 === $t || $from === $to) {
            return $from;
        }
        if (1.0 === $t) {
            return $to;
        }
        // visibility is visible between a visible end and a hidden one, like CSS
        if (\in_array([strtolower(trim($from)), strtolower(trim($to))], [['visible', 'hidden'], ['hidden', 'visible'], ['visible', 'collapse'], ['collapse', 'visible']], true)) {
            return $t > 0.0 && $t < 1.0 ? 'visible' : ($t <= 0.0 ? $from : $to);
        }
        if (($a = Value::color($from, $media)) instanceof Color && ($b = Value::color($to, $media)) instanceof Color) {
            // Colors stay between: an easing that overshoots, like cubic-bezier(.5, -1, .5, 2), does not take them further
            return $a->mix($b, (int) round(max(0.0, min(1.0, $t)) * 100))->toHex();
        }

        return self::combine($from, $to, $media, static fn (float $a, float $b, string $unit): string => self::format($a + ($b - $a) * $t).$unit, static fn (float $a, string $unitA, float $b, string $unitB): string => 'calc('.self::format($a).$unitA.' * '.self::format(1 - $t).' + '.self::format($b).$unitB.' * '.self::format($t).')', static fn (Color $a, Color $b): string => $a->mix($b, (int) round(max(0.0, min(1.0, $t)) * 100))->toHex()) ?? ($t < 0.5 ? $from : $to);
    }

    /** The units of lengths, which calc() adds to each other and to percentages */
    private const LENGTH_UNITS = ['%', 'px', 'ch', 'lh', 'rlh', 'em', 'rem', 'ex', 'ic', 'cap', 'in', 'cm', 'mm', 'q', 'pt', 'pc', 'vw', 'vh', 'vi', 'vb', 'vmin', 'vmax', 'svw', 'svh', 'lvw', 'lvh', 'dvw', 'dvh', 'cqw', 'cqh', 'cqi', 'cqb', 'cqmin', 'cqmax'];
    /** The functions whose whole value is a color */
    private const COLOR_FUNCTIONS = ['rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color', 'color-mix', 'light-dark'];

    /**
     * Two values of the same shape, the same words and punctuation around numbers and colors, joined part by part;
     * null when their shapes differ, or two numbers cannot be joined.
     *
     * @param \Closure(float, float, string): string                $numbers Two numbers of one unit
     * @param (\Closure(float, string, float, string): string)|null $lengths Two lengths of different units
     * @param \Closure(Color, Color): string                        $colors
     */
    private static function combine(string $from, string $to, MediaContext $media, \Closure $numbers, ?\Closure $lengths, \Closure $colors): ?string
    {
        if (null === ($a = self::parts($from, $media)) || null === ($b = self::parts($to, $media)) || \count($a) !== \count($b) || \count($a) > 256) {
            return null;
        }
        $value = '';
        foreach ($a as $i => [$type, $x, $unitX]) {
            [$otherType, $y, $unitY] = $b[$i];
            if ($type !== $otherType) {
                return null;
            }
            if ('color' === $type) {
                // The same color keeps its name, like an ANSI one
                $value .= $unitX === $unitY ? $unitX : $colors($x, $y);
                continue;
            }
            if ('other' === $type) {
                if ($x !== $y) {
                    return null;
                }
                $value .= $x;
                continue;
            }
            // A 0 without a unit is a length of the other's unit
            if ($unitX !== $unitY && 0.0 === $x && '' === $unitX && \in_array($unitY, self::LENGTH_UNITS, true)) {
                $unitX = $unitY;
            } elseif ($unitX !== $unitY && 0.0 === $y && '' === $unitY && \in_array($unitX, self::LENGTH_UNITS, true)) {
                $unitY = $unitX;
            }
            if ($unitX === $unitY) {
                $value .= $numbers($x, $y, $unitX);
            } elseif (null !== $lengths && \in_array($unitX, self::LENGTH_UNITS, true) && \in_array($unitY, self::LENGTH_UNITS, true)) {
                $value .= $lengths($x, $unitX, $y, $unitY);
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * A value cut into numbers (with their unit, lowercased), colors (with their text), and the rest: words, strings, url() and punctuation,
     * spaces made one; null for a value with var(), which is not one yet.
     *
     * @return list<array{'number', float, string}|array{'color', Color, string}|array{'other', string, ''}>|null
     */
    private static function parts(string $value, MediaContext $media): ?array
    {
        $value = trim($value);
        $length = \strlen($value);
        $parts = [];
        $offset = 0;
        while ($offset < $length) {
            if (!preg_match('/\G(?:(?<space>\s+)|(?<string>"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')|(?<url>url\([^)]*\))|(?<number>[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?)(?<unit>%|[a-z]+)?|(?<hex>#[0-9a-f]+)|(?<function>-?[a-z_][\w-]*)\(|(?<word>-?[a-z_][\w-]*)|(?<char>.))/Ais', $value, $m, 0, $offset)) {
                return null;
            }
            $offset += \strlen($m[0]);
            if ('' !== ($m['space'] ?? '')) {
                $parts[] = ['other', ' ', ''];
            } elseif ('' !== ($m['number'] ?? '')) {
                $parts[] = ['number', (float) $m['number'], strtolower($m['unit'] ?? '')];
            } elseif ('' !== ($m['function'] ?? '')) {
                $name = strtolower($m['function']);
                if ('var' === $name || 'env' === $name || 'attr' === $name) {
                    return null;
                }
                if (!\in_array($name, self::COLOR_FUNCTIONS, true)) {
                    $parts[] = ['other', $name.'(', ''];
                    continue;
                }
                // The whole color function, its parentheses balanced
                $start = $offset - \strlen($m[0]);
                for ($depth = 1; $offset < $length && $depth > 0; ++$offset) {
                    $depth += match ($value[$offset]) {
                        '(' => 1,
                        ')' => -1,
                        default => 0,
                    };
                }
                if (!($color = Value::color(substr($value, $start, $offset - $start), $media)) instanceof Color) {
                    return null;
                }
                $parts[] = ['color', $color, strtolower(substr($value, $start, $offset - $start))];
            } elseif ('' !== ($word = $m['hex'] ?? '') || '' !== ($word = $m['word'] ?? '')) {
                $parts[] = ($color = Value::color($word, $media)) instanceof Color ? ['color', $color, strtolower($word)] : ['other', strtolower($word), ''];
            } else {
                $parts[] = ['other', $m[0], ''];
            }
        }

        return $parts;
    }

    private static function format(float $number): string
    {
        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * A value added to another, like animation-composition: add: numbers of the same unit summed, colors channel by
     * channel, clamped; other values are replaced.
     */
    public static function add(string $underlying, string $value, MediaContext $media): string
    {
        return self::combine($underlying, $value, $media, static fn (float $a, float $b, string $unit): string => self::format($a + $b).$unit, static fn (float $a, string $unitA, float $b, string $unitB): string => 'calc('.self::format($a).$unitA.' + '.self::format($b).$unitB.')', static function (Color $a, Color $b): string {
            ['r' => $r, 'g' => $g, 'b' => $bl] = $a->toRgb();
            ['r' => $r2, 'g' => $g2, 'b' => $b2] = $b->toRgb();

            return Color::rgb(min(255, $r + $r2), min(255, $g + $g2), min(255, $bl + $b2))->toHex();
        }) ?? $value;
    }

    /**
     * The progress of a cycle, by direction.
     */
    private function directed(float $progress, int $cycle): float
    {
        $reversed = match ($this->direction) {
            'reverse' => true,
            'alternate' => 1 === $cycle % 2,
            'alternate-reverse' => 0 === $cycle % 2,
            default => false,
        };

        return $reversed ? 1.0 - $progress : $progress;
    }

    /**
     * Whether two values are in between each other: two colors, or values of the same shape whose numbers or colors differ.
     */
    public static function isInterpolable(string $from, string $to, MediaContext $media): bool
    {
        if (Value::color($from, $media) instanceof Color && Value::color($to, $media) instanceof Color) {
            return true;
        }

        // Of the same shape, with something in between: equal ones are not
        $half = self::between($from, $to, 0.5, $media);

        return $half !== $from && $half !== $to;
    }

    /**
     * A timing function, cut into steps: smooth ones in as many as bound the styles computed.
     *
     * @param array{0: string, 1: int, 2: 'start'|'end'|'none'|'both', 3?: list<float>|list<array{float, float}>} $timing
     */
    public static function ease(array $timing, float $t): float
    {
        [$function, $steps, $position] = $timing;
        $t = match (true) {
            // Like steps(): jump-start jumps at the start of each step, jump-end at its end, jump-none holds the first and last
            // values for a step each, jump-both jumps at both ends
            'steps' === $function => min(1.0, match ($position) {
                'start' => ceil($t * $steps) / $steps,
                'none' => floor($t * $steps) / ($steps - 1),
                'both' => (floor($t * $steps) + 1) / ($steps + 1),
                default => floor($t * $steps) / $steps,
            }),
            'linear' === $function => isset($timing[3]) ? self::piecewise($timing[3], $t) : $t,
            default => self::bezier($timing[3] ?? self::BEZIERS[$function] ?? self::BEZIERS['ease'], $t),
        };

        return 'steps' === $function ? $t : round($t * self::STEPS) / self::STEPS;
    }

    /**
     * Converts a time to seconds: 1s, 100ms, or a math function over times, like calc(sibling-index() * 100ms).
     */
    public static function time(string $value): ?float
    {
        return match (true) {
            1 === preg_match('/^(-?\d*\.?\d+)ms$/', $value, $m) => (float) $m[1] / 1000,
            1 === preg_match('/^(-?\d*\.?\d+)s$/', $value, $m) => (float) $m[1],
            Length::isMath($value) && 1 === preg_match('/\d(?:m?s)\b/i', $value) => Length::number(preg_replace_callback('/(\d*\.?\d+(?:e[+-]?\d+)?)(ms|s)\b/i', static fn (array $m) => 'ms' === strtolower($m[2]) ? (string) ((float) $m[1] / 1000) : $m[1], $value)),
            default => null,
        };
    }

    /**
     * @return array{0: string, 1: int, 2: 'start'|'end'|'none'|'both', 3?: list<float>|list<array{float, float}>}|null
     */
    public static function timing(string $value): ?array
    {
        $value = strtolower(trim($value));

        return match (true) {
            'linear' === $value => ['linear', 0, 'end'],
            isset(self::BEZIERS[$value]) => [$value, 0, 'end'],
            'step-start' === $value => ['steps', 1, 'start'],
            'step-end' === $value => ['steps', 1, 'end'],
            // jump-none needs two steps at least: one would never change
            1 === preg_match('/^steps\(\s*(\d{1,4})\s*(?:,\s*(jump-start|start|jump-end|end|jump-none|jump-both))?\s*\)$/', $value, $m) && (int) $m[1] > ('jump-none' === ($m[2] ?? '') ? 1 : 0) => ['steps', (int) $m[1], match ($m[2] ?? 'end') {
                'start', 'jump-start' => 'start',
                'jump-none' => 'none',
                'jump-both' => 'both',
                default => 'end',
            }],
            1 === preg_match('/^cubic-bezier\((.*)\)$/s', $value, $m) => self::cubicBezier($m[1]),
            1 === preg_match('/^linear\((.*)\)$/s', $value, $m) => self::linearStops($m[1]),
            default => null,
        };
    }

    /**
     * @return array{string, int, 'end', list<float>}|null
     */
    private static function cubicBezier(string $arguments): ?array
    {
        $points = Selector::splitTopLevel($arguments);
        if (4 !== \count($points) || !array_all($points, static fn (string $p) => is_numeric($p))) {
            return null;
        }
        $points = array_map(floatval(...), $points);
        // The abscissas are times: they stay within the iteration
        if ($points[0] < 0 || $points[0] > 1 || $points[2] < 0 || $points[2] > 1) {
            return null;
        }

        return ['cubic-bezier', 0, 'end', $points];
    }

    /**
     * The points of linear(): each stop an output, at one or two input percentages, the missing inputs spread evenly.
     *
     * @return array{string, int, 'end', list<array{float, float}>}|null
     */
    private static function linearStops(string $arguments): ?array
    {
        $stops = [];
        foreach (Selector::splitTopLevel($arguments) as $stop) {
            $parts = Value::split($stop);
            if (!$parts || \count($parts) > 3 || !is_numeric($parts[0])) {
                return null;
            }
            $inputs = [];
            foreach (\array_slice($parts, 1) as $part) {
                if (!preg_match('/^(-?\d*\.?\d+)%$/', $part, $m)) {
                    return null;
                }
                $inputs[] = (float) $m[1] / 100;
            }
            foreach ($inputs ?: [null] as $input) {
                $stops[] = [$input, (float) $parts[0]];
            }
        }
        if (\count($stops) < 2) {
            return null;
        }

        $last = \count($stops) - 1;
        $stops[0][0] ??= 0.0;
        $stops[$last][0] ??= 1.0;
        // An input is never before the ones of the stops before it
        $largest = $stops[0][0];
        foreach ($stops as $i => [$input]) {
            if (null !== $input) {
                $stops[$i][0] = $largest = max($largest, $input);
            }
        }
        // Runs of missing inputs are spread evenly between their neighbors
        for ($i = 1; $i < $last; ++$i) {
            if (null !== $stops[$i][0]) {
                continue;
            }
            $end = $i;
            while (null === $stops[$end][0]) {
                ++$end;
            }
            $from = $stops[$i - 1][0];
            for ($j = $i; $j < $end; ++$j) {
                $stops[$j][0] = $from + ($stops[$end][0] - $from) * ($j - $i + 1) / ($end - $i + 1);
            }
            $i = $end;
        }

        return ['linear', 0, 'end', $stops];
    }

    /**
     * The output of a cubic Bézier curve at an input, solved by Newton's method, bisection when it does not converge.
     *
     * @param list<float> $points x1, y1, x2, y2
     */
    private static function bezier(array $points, float $x): float
    {
        [$x1, $y1, $x2, $y2] = $points;
        $curve = static fn (float $t, float $p1, float $p2): float => ((1 - 3 * $p2 + 3 * $p1) * $t + (3 * $p2 - 6 * $p1)) * $t * $t + 3 * $p1 * $t;
        if ($x <= 0.0 || $x >= 1.0) {
            // Outside of the iteration, the curve goes on along its end tangents
            $slope = $x <= 0.0 ? ($x1 > 0 ? $y1 / $x1 : ($x2 > 0 ? $y2 / $x2 : 0.0)) : ($x2 < 1 ? (1 - $y2) / (1 - $x2) : ($x1 < 1 ? (1 - $y1) / (1 - $x1) : 0.0));

            return $x <= 0.0 ? $slope * $x : 1 + $slope * ($x - 1);
        }

        $t = $x;
        for ($i = 0; $i < 8; ++$i) {
            $error = $curve($t, $x1, $x2) - $x;
            if (abs($error) < 1e-7) {
                return $curve($t, $y1, $y2);
            }
            $derivative = (3 * (1 - 3 * $x2 + 3 * $x1) * $t + 2 * (3 * $x2 - 6 * $x1)) * $t + 3 * $x1;
            if (abs($derivative) < 1e-6) {
                break;
            }
            $t -= $error / $derivative;
        }

        $low = 0.0;
        $high = 1.0;
        $t = $x;
        for ($i = 0; $i < 64 && $high - $low > 1e-7; ++$i) {
            $t = ($low + $high) / 2;
            if ($curve($t, $x1, $x2) < $x) {
                $low = $t;
            } else {
                $high = $t;
            }
        }

        return $curve($t, $y1, $y2);
    }

    /**
     * The output of linear() at an input, along the line of the two points around it, or of the first or last two.
     *
     * @param list<array{float, float}> $points
     */
    private static function piecewise(array $points, float $x): float
    {
        $count = \count($points);
        $segment = $x < $points[0][0] ? 0 : $count - 2;
        for ($i = 0; $i < $count - 1; ++$i) {
            if ($x >= $points[$i][0] && $x <= $points[$i + 1][0]) {
                // The last point at an input wins
                $segment = $i;
                if ($x < $points[$i + 1][0]) {
                    break;
                }
            }
        }
        [[$x0, $y0], [$x1, $y1]] = [$points[$segment], $points[$segment + 1]];

        return $x1 === $x0 ? $y1 : $y0 + ($y1 - $y0) * ($x - $x0) / ($x1 - $x0);
    }
}
