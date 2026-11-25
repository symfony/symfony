<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

/**
 * Resolves automatic scrollbar states, initially without automatic gutters.
 *
 * A repeated state locks only the axes varying within that cycle to their
 * observed union. This deterministic fallback is a device policy, not a claim
 * that the CSS constraints have a fixed point. The finite pass budget bounds
 * resource use; it is not a convergence proof.
 *
 * @internal
 */
final class ScrollbarState
{
    private array $history = [];
    private array $seen = [];
    private int $passes = 0;
    private int $candidates = 0;
    private ?int $budget = null;

    public function begin(int $candidates): void
    {
        $this->budget ??= max(8, 4 * $candidates + 1);
        if (++$this->passes > $this->budget) {
            throw new \LengthException('Scrollbar layout exceeded its state resolution budget.');
        }
    }

    public function settle(array &$policies, array $required): bool
    {
        if ($this->candidates !== \count($policies)) {
            $this->history = $this->seen = [];
            $this->candidates = \count($policies);
        }
        $used = $next = [];
        foreach ($policies as $key => $policy) {
            $used[$key] = [$policy['x'], $policy['y']];
            $wanted = $required[$key] ?? [false, false];
            $next[$key] = [$policy['lock-x'] || $wanted[0], $policy['lock-y'] || $wanted[1]];
        }
        if ($used === $next) {
            return true;
        }
        $fingerprint = $this->encode($used);
        $this->seen[$fingerprint] = \count($this->history);
        $this->history[] = $fingerprint;
        $fingerprint = $this->encode($next);
        if (isset($this->seen[$fingerprint])) {
            $cycle = \array_slice($this->history, $this->seen[$fingerprint]);
            $position = 0;
            foreach ($policies as $key => &$policy) {
                foreach ([0 => 'x', 1 => 'y'] as $axis => $name) {
                    $values = array_map(static fn (string $state): int => (\ord($state[$position]) >> $axis) & 1, $cycle);
                    if (\count(array_unique($values)) > 1) {
                        $policy['lock-'.$name] = true;
                        $next[$key][$axis] = true;
                    }
                }
                ++$position;
            }
            unset($policy);
            $this->history = $this->seen = [];
        }
        foreach ($next as $key => [$horizontal, $vertical]) {
            $policies[$key]['x'] = $horizontal;
            $policies[$key]['y'] = $vertical;
        }

        return false;
    }

    private function encode(array $state): string
    {
        $bits = '';
        foreach ($state as [$horizontal, $vertical]) {
            $bits .= \chr((int) $horizontal | ((int) $vertical << 1));
        }

        return $bits;
    }
}
