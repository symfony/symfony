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

/**
 * Evaluates CSS math while the rendering context resolves primitive units.
 *
 * @internal
 */
final class Length
{
    public const MATH_FUNCTIONS = ['calc', 'min', 'max', 'clamp', 'round', 'mod', 'rem', 'abs', 'sign', 'pow', 'sqrt', 'hypot', 'exp', 'log', 'sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'atan2'];

    public static function absolute(float $number, string $unit): ?float
    {
        return match ($unit) {
            'px' => $number,
            'pt' => $number * 4 / 3,
            'pc' => $number * 16,
            'in' => $number * 96,
            'cm' => $number * 96 / 2.54,
            'mm' => $number * 96 / 25.4,
            'q' => $number * 96 / 101.6,
            default => null,
        };
    }

    private int $depth = 0;

    private function __construct(private readonly \Closure $resolver, private ?string $angles)
    {
    }

    public static function evaluate(string $value, \Closure $resolver, ?string $angles = null): ?float
    {
        if (\strlen($value) > 65536) {
            return null;
        }
        $evaluator = new self($resolver, $angles);
        $value = trim($value);
        if (!self::isMath($value)) {
            return $evaluator->resolve($value);
        }
        $tokens = $evaluator->tokenize($value);
        if (null === $tokens || \count($tokens) > 2048) {
            return null;
        }
        foreach ($tokens as $token) {
            if (str_ends_with($token, '(') && ++$evaluator->depth > 64) {
                return null;
            }
            if (')' === $token) {
                --$evaluator->depth;
            }
        }
        $position = 0;
        $result = $evaluator->parseSum($tokens, $position);

        return $position === \count($tokens) ? $result : null;
    }

    public static function linear(\Closure $at): ?array
    {
        $zero = $at(0.0);
        $hundred = $at(100.0);
        if (null === $zero || null === $hundred || !is_finite($zero) || !is_finite($hundred)) {
            return null;
        }
        foreach ([7.0, 33.0, 1000.0] as $room) {
            $size = $at($room);
            if (null === $size || !is_finite($size) || abs($size - $zero - $room / 100 * ($hundred - $zero)) > 0.001) {
                return null;
            }
        }

        return [max(-1000.0, min(1000.0, $hundred - $zero)), max(-10000.0, min(10000.0, $zero))];
    }

    public static function isMath(string $value): bool
    {
        return (bool) preg_match('/^(?:'.implode('|', self::MATH_FUNCTIONS).')\(/i', $value);
    }

    private function resolve(string $value): ?float
    {
        if (preg_match('/^(-?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?)(deg|grad|rad|turn)$/i', $value, $m)) {
            if (null === $this->angles) {
                return null;
            }
            $degrees = (float) $m[1] * match (strtolower($m[2])) {
                'grad' => 0.9,
                'rad' => 180 / \M_PI,
                'turn' => 360,
                default => 1,
            };

            return 'rad' === $this->angles ? deg2rad($degrees) : $degrees;
        }

        return ($this->resolver)($value);
    }

    /**
     * @return list<string>|null
     */
    private function tokenize(string $value): ?array
    {
        if (!preg_match_all('/\s*((?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?[a-z%]*|[a-z][a-z0-9-]*\(|[a-z][a-z-]*|[()+*\/,-])/iA', $value, $m) || implode('', $m[0]) !== rtrim($value)) {
            return null;
        }

        return array_map('strtolower', $m[1]);
    }

    /**
     * @param list<string> $tokens
     */
    private function parseSum(array $tokens, int &$position): ?float
    {
        $result = $this->parseProduct($tokens, $position);
        while (null !== $result && \in_array($tokens[$position] ?? null, ['+', '-'], true)) {
            $operator = $tokens[$position++];
            $operand = $this->parseProduct($tokens, $position);
            if (null === $operand) {
                return null;
            }
            $result = '+' === $operator ? $result + $operand : $result - $operand;
        }

        return $result;
    }

    /**
     * @param list<string> $tokens
     */
    private function parseProduct(array $tokens, int &$position): ?float
    {
        $result = $this->parseFactor($tokens, $position);
        while (null !== $result && \in_array($tokens[$position] ?? null, ['*', '/'], true)) {
            $operator = $tokens[$position++];
            $operand = $this->parseFactor($tokens, $position);
            if (null === $operand || '/' === $operator && 0.0 === $operand) {
                return null;
            }
            $result = '*' === $operator ? $result * $operand : $result / $operand;
        }

        return $result;
    }

    /**
     * @param list<string> $tokens
     */
    private function parseFactor(array $tokens, int &$position): ?float
    {
        $token = $tokens[$position++] ?? null;
        if ('-' === $token) {
            $factor = $this->parseFactor($tokens, $position);

            return null === $factor ? null : -$factor;
        }
        if ('(' === $token || 'calc(' === $token) {
            $result = $this->parseSum($tokens, $position);

            return ')' === ($tokens[$position++] ?? null) ? $result : null;
        }
        // The constants of CSS Values
        $constant = match ($token) {
            'e' => \M_E,
            'pi' => \M_PI,
            'infinity' => \INF,
            'nan' => \NAN,
            default => null,
        };
        if (null !== $constant) {
            return $constant;
        }
        if (null !== $token && str_ends_with($token, '(') && \in_array($function = substr($token, 0, -1), self::MATH_FUNCTIONS, true)) {
            return $this->parseFunction($function, $tokens, $position);
        }

        return null === $token || str_ends_with($token, '(') ? null : $this->resolve($token);
    }

    /**
     * Evaluates a math function from its comma-separated arguments.
     *
     * @param list<string> $tokens
     */
    private function parseFunction(string $function, array $tokens, int &$position): ?float
    {
        // round() starts with its rounding strategy
        $strategy = 'nearest';
        if ('round' === $function && \in_array($tokens[$position] ?? null, ['nearest', 'up', 'down', 'to-zero'], true)) {
            $strategy = $tokens[$position++];
            if (',' !== ($tokens[$position++] ?? null)) {
                return null;
            }
        }
        $angles = $this->angles;
        // Angles in radians for a trigonometric function, through the functions nested in it too
        if (\in_array($function, ['sin', 'cos', 'tan'], true)) {
            $this->angles = 'rad';
        }
        try {
            $arguments = [];
            do {
                $argument = $this->parseSum($tokens, $position);
                if (null === $argument) {
                    return null;
                }
                $arguments[] = $argument;
            } while (',' === ($tokens[$position] ?? null) && ++$position);
        } finally {
            $this->angles = $angles;
        }
        if (')' !== ($tokens[$position++] ?? null)) {
            return null;
        }
        $count = \count($arguments);
        [$a, $b] = $arguments + [1 => null];

        return match ($function) {
            'calc' => 1 === $count ? $a : null,
            'min' => min($arguments),
            'max' => max($arguments),
            'clamp' => 3 === $count ? max($arguments[0], min($arguments[1], $arguments[2])) : null,
            // Rounded to a multiple of the step, 1 by default: a whole number of cells
            'round' => $count <= 2 ? $this->round($strategy, $a, $b ?? 1.0) : null,
            'mod' => 2 === $count ? 0.0 === $b ? \NAN : $a - $b * floor($a / $b) : null,
            'rem' => 2 === $count ? 0.0 === $b ? \NAN : fmod($a, $b) : null,
            'abs' => 1 === $count ? abs($a) : null,
            'sign' => 1 === $count ? ($a <=> 0.0) * 1.0 : null,
            'pow' => 2 === $count ? $a ** $b : null,
            'sqrt' => 1 === $count ? $a < 0 ? \NAN : sqrt($a) : null,
            'hypot' => sqrt(array_sum(array_map(static fn (float $x): float => $x * $x, $arguments))),
            'exp' => 1 === $count ? exp($a) : null,
            'log' => $count <= 2 ? $a <= 0 ? $a < 0 ? \NAN : -\INF : (null === $b ? log($a) : log($a, $b)) : null,
            'sin' => 1 === $count ? sin($a) : null,
            'cos' => 1 === $count ? cos($a) : null,
            'tan' => 1 === $count ? tan($a) : null,
            // Angles, in degrees
            'asin' => 1 === $count ? rad2deg(asin($a)) : null,
            'acos' => 1 === $count ? rad2deg(acos($a)) : null,
            'atan' => 1 === $count ? rad2deg(atan($a)) : null,
            'atan2' => 2 === $count ? rad2deg(atan2($a, $b)) : null,
            default => null,
        };
    }

    /**
     * Rounds to a multiple of the step, like round() of CSS Values.
     */
    private function round(string $strategy, float $value, float $step): float
    {
        if (0.0 === $step) {
            return \NAN;
        }
        $step = abs($step);
        if (!is_finite($value)) {
            return $value;
        }
        if (!is_finite($step)) {
            // Infinitely large steps: 0, or the infinity the strategy goes to
            return match ($strategy) {
                'up' => $value > 0 ? \INF : 0.0,
                'down' => $value < 0 ? -\INF : 0.0,
                default => 0.0,
            };
        }
        $multiple = $value / $step;

        return $step * match ($strategy) {
            'up' => ceil($multiple),
            'down' => floor($multiple),
            'to-zero' => $multiple < 0 ? ceil($multiple) : floor($multiple),
            // Half way rounds up, towards positive infinity
            default => floor($multiple + 0.5),
        };
    }
}
