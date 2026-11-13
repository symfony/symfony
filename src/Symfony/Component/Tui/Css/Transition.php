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

/**
 * The CSS transitions of an element: for each property, how long a change of its value takes.
 *
 * @internal
 */
final class Transition
{
    /**
     * @param array<string, array{float, float, array{0: string, 1: int, 2: 'start'|'end'|'none'|'both', 3?: list<float>, points?: list<array{float, float}>}, bool}> $properties Duration, delay, timing and whether discrete values change halfway (allow-discrete) by property, "all" for every one
     */
    private function __construct(
        public readonly array $properties,
    ) {
    }

    /**
     * @param array<string, string> $computed
     */
    public static function fromDeclarations(array $computed): ?self
    {
        $list = static fn (string $property): array => isset($computed[$property]) ? array_map(static fn (string $v) => strtolower(trim($v)), Selector::splitTopLevel($computed[$property])) : [];

        // The shorthand, one transition per comma, then the longhands, repeated like CSS lists
        $transitions = [];
        foreach ($list('transition') as $layer) {
            $property = 'all';
            $times = [];
            $timing = ['ease', 0, 'end'];
            $discrete = false;
            foreach (Value::split($layer) as $part) {
                if (null !== $time = Animation::time($part)) {
                    $times[] = $time;
                } elseif (null !== $t = Animation::timing($part)) {
                    $timing = $t;
                } elseif ('allow-discrete' === $part) {
                    $discrete = true;
                } elseif ('normal' !== $part) {
                    $property = $part;
                }
            }
            $transitions[] = [$property, $times[0] ?? 0.0, $times[1] ?? 0.0, $timing, $discrete];
        }
        foreach (['transition-property' => 0, 'transition-duration' => 1, 'transition-delay' => 2, 'transition-timing-function' => 3, 'transition-behavior' => 4] as $longhand => $index) {
            $values = $list($longhand);
            if (!$values) {
                continue;
            }
            if (0 === $index) {
                $transitions = array_map(static fn (string $property, int $i) => [$property, ...\array_slice($transitions[$i] ?? ['', 0.0, 0.0, ['ease', 0, 'end'], false], 1)], $values, array_keys($values));
                continue;
            }
            foreach ($transitions as $i => $transition) {
                $value = $values[$i % \count($values)];
                $transition[$index] = match ($index) {
                    3 => Animation::timing($value) ?? $transition[3],
                    4 => 'allow-discrete' === $value,
                    default => Animation::time($value) ?? $transition[$index],
                };
                $transitions[$i] = $transition;
            }
        }

        $properties = [];
        foreach (\array_slice($transitions, 0, 32) as [$property, $duration, $delay, $timing, $discrete]) {
            if ('none' !== $property && '' !== $property && $duration > 0) {
                $properties[$property] = [min($duration, 3600.0), max(-3600.0, min(3600.0, $delay)), $timing, $discrete];
            }
        }

        return $properties ? new self($properties) : null;
    }

    /**
     * How a property is transitioned, null when it is not.
     *
     * @return array{float, float, array{0: string, 1: int, 2: 'start'|'end'|'none'|'both', 3?: list<float>, points?: list<array{float, float}>}, bool}|null
     */
    public function of(string $property): ?array
    {
        return $this->properties[$property] ?? $this->properties['all'] ?? null;
    }
}
