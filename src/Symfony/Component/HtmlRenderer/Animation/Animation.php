<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Animation;

use Symfony\Component\HtmlRenderer\Css\Length;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Css\ValueTokenizer;

/**
 * A CSS animation definition and its progress on an explicit timeline.
 *
 * @internal
 */
class Animation
{
    protected const INITIAL = ['opacity' => '1', 'letter-spacing' => '0', 'text-indent' => '0', 'margin-left' => '0', 'margin-right' => '0', 'margin-top' => '0', 'margin-bottom' => '0', 'padding-left' => '0', 'padding-right' => '0', 'padding-top' => '0', 'padding-bottom' => '0', 'background-color' => 'transparent', 'top' => '0', 'left' => '0', 'right' => '0', 'bottom' => '0'];

    public function isScrollDriven(): bool
    {
        return 'auto' !== $this->timeline && 'none' !== $this->timeline;
    }

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

    public static function rangeEdge(string $value, float $default): ?array
    {
        $parts = ValueTokenizer::split(strtolower(trim($value)));
        $name = 'normal';
        $percent = $default;
        if (isset($parts[0]) && \in_array($parts[0], ['normal', 'cover', 'contain', 'entry', 'exit', 'entry-crossing', 'exit-crossing'], true)) {
            $name = array_shift($parts);
        }
        if (isset($parts[0]) && preg_match('/^(-?\d*\.?\d+)%$/', $parts[0], $m)) {
            $percent = (float) $m[1];
            array_shift($parts);
        }

        return [] === $parts && ('normal' !== $name || [] !== ValueTokenizer::split($value)) ? [$name, $percent] : null;
    }

    public static function listFromDeclarations(array $computed): array
    {
        $layers = [];
        foreach (Selector::splitTopLevel($computed['animation'] ?? '') as $layer) {
            $layers[] = self::layer($layer);
        }
        $list = static fn (string $property): array => isset($computed[$property]) ? array_map(static fn (string $v) => 'animation-name' === $property || 'animation-timeline' === $property && str_starts_with(trim($v), '--') ? trim($v) : strtolower(trim($v)), Selector::splitTopLevel($computed[$property])) : [];
        // animation-name sets how many animations there are; the other lists repeat or are cut to it, like CSS
        if ($names = $list('animation-name')) {
            $layers = array_map(static fn (string $name, int $i) => ['name' => $name] + ($layers[$i] ?? self::layer('')), $names, array_keys($names));
        }
        if (!$layers) {
            return [];
        }
        foreach (['animation-duration' => static fn (string $v) => ['duration' => self::time($v)], 'animation-delay' => static fn (string $v) => ['delay' => self::time($v)], 'animation-iteration-count' => static fn (string $v) => ['iterations' => 'infinite' === $v ? \INF : max(0.0, (float) $v)], 'animation-direction' => static fn (string $v) => ['direction' => $v], 'animation-fill-mode' => static fn (string $v) => ['fill' => $v], 'animation-timing-function' => static fn (string $v) => null !== ($t = self::timing($v)) ? ['timing' => $t] : [], 'animation-play-state' => static fn (string $v) => ['paused' => 'paused' === $v], 'animation-timeline' => static fn (string $v) => ['timeline' => $v], 'animation-range' => static fn (string $v) => null !== ($range = self::range($v)) ? ['range' => $range] : [], 'animation-range-start' => static fn (string $v) => null !== ($edge = self::rangeEdge($v, 0.0)) ? ['rangeStart' => $edge] : [], 'animation-range-end' => static fn (string $v) => null !== ($edge = self::rangeEdge($v, 100.0)) ? ['rangeEnd' => $edge] : [], 'animation-composition' => static fn (string $v) => \in_array($v, ['replace', 'add', 'accumulate'], true) ? ['composition' => $v] : []] as $property => $read) {
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
            if (null === $name || 'none' === $name || !$scrolled && (null === $duration || $duration <= 0.0)) {
                continue;
            }
            $range = $layer['range'] ?? [['normal', 0.0], ['normal', 100.0]];
            $range = [$layer['rangeStart'] ?? $range[0], $layer['rangeEnd'] ?? $range[1]];
            $occurrence = $occurrences[$name] = isset($occurrences[$name]) ? $occurrences[$name] + 1 : 0;
            $animations[$name.'#'.$occurrence] = new static($name, $scrolled ? 1.0 : min($duration, 86400.0), $scrolled ? 0.0 : max(-86400.0, min(86400.0, $delay ?? 0.0)), $layer['iterations'], $layer['direction'], $layer['fill'], $layer['timing'], $layer['paused'], $timeline, $range, $layer['composition'] ?? 'replace');
        }

        return $animations;
    }

    public static function fromDeclarations(array $computed): ?self
    {
        return array_values(self::listFromDeclarations($computed))[0] ?? null;
    }

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

    public function isRunning(float $elapsed): bool
    {
        return !$this->paused && ($elapsed - $this->delay) / $this->duration < $this->iterations;
    }

    /**
     * @param \Closure(string, string, float, string): string $between The fourth argument is the CSS property
     */
    public function valuesAt(array $keyframes, float $progress, array $computed, \Closure $between, ?\Closure $add = null): array
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
                    $frames = array_map(static fn (array $frame) => [$frame[0], null !== $add ? $add($own, $frame[1]) : $frame[1]], $frames);
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
            $values[$property] = $between($before[1], $after[1], $span > 0 ? static::ease($this->timing, ($progress - $before[0]) / $span) : 0.0, $property);
        }

        return $values;
    }

    public static function ease(array $timing, float $t): float
    {
        return Easing::evaluate($timing, $t);
    }

    public static function time(string $value): ?float
    {
        return match (true) {
            1 === preg_match('/^(-?\d*\.?\d+)ms$/', $value, $m) => (float) $m[1] / 1000,
            1 === preg_match('/^(-?\d*\.?\d+)s$/', $value, $m) => (float) $m[1],
            Length::isMath($value) && 1 === preg_match('/\d(?:m?s)\b/i', $value) => self::number(preg_replace_callback('/(\d*\.?\d+(?:e[+-]?\d+)?)(ms|s)\b/i', static fn (array $m) => 'ms' === strtolower($m[2]) ? (string) ((float) $m[1] / 1000) : $m[1], $value)),
            default => null,
        };
    }

    public static function timing(string $value): ?array
    {
        return Easing::parse($value);
    }

    final protected function __construct(public readonly string $name, public readonly float $duration, public readonly float $delay, public readonly float $iterations, public readonly string $direction, public readonly string $fill, public readonly array $timing, public readonly bool $paused, public readonly string $timeline = 'auto', public readonly array $range = [['normal', 0.0], ['normal', 100.0]], public readonly string $composition = 'replace')
    {
    }

    private static function range(string $value): ?array
    {
        $parts = ValueTokenizer::split(strtolower(trim($value)));
        $names = ['normal', 'cover', 'contain', 'entry', 'exit', 'entry-crossing', 'exit-crossing'];
        // A lone range name: the whole of it
        if (1 === \count($parts) && \in_array($parts[0], $names, true)) {
            return [[$parts[0], 0.0], [$parts[0], 100.0]];
        }
        // The start, then the end, each a name and a percentage, or one of them
        $edges = [];
        for ($i = 0, $count = \count($parts); $i < $count; ++$i) {
            $part = $parts[$i];
            if (\in_array($part, $names, true)) {
                $percent = null;
                if (isset($parts[$i + 1]) && preg_match('/^(-?\d*\.?\d+)%$/', $parts[$i + 1], $m)) {
                    $percent = (float) $m[1];
                    ++$i;
                }
                $edges[] = [$part, $percent];
            } elseif (preg_match('/^(-?\d*\.?\d+)%$/', $part, $m)) {
                $edges[] = ['normal', (float) $m[1]];
            } else {
                return null;
            }
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
        foreach (ValueTokenizer::split($shorthand) as $part) {
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

    private static function number(string $value): ?float
    {
        $number = Length::evaluate($value, static fn (string $token): ?float => is_numeric($token) ? (float) $token : null);

        return null === $number ? null : (is_nan($number) ? 0.0 : max(-1000000000.0, min(1000000000.0, $number)));
    }
}
