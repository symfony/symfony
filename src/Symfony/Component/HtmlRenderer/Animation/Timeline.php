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

/**
 * Retains document animation state while the embedding runtime advances time.
 *
 * @internal
 */
final class Timeline
{
    private \WeakMap $elements;

    /**
     * @param (\Closure(array): array<Animation>)|null               $definitions
     * @param (\Closure(string, string, float, string): string)|null $between
     * @param (\Closure(string, string): string)|null                $add
     * @param (\Closure(string, string): bool)|null                  $interpolable
     * @param (\Closure(array, float): float)|null                   $ease
     * @param (\Closure(float): float)|null                          $progress
     */
    public function __construct(
        private readonly ?\Closure $definitions = null,
        private readonly ?\Closure $between = null,
        private readonly ?\Closure $add = null,
        private readonly ?\Closure $interpolable = null,
        private readonly ?\Closure $ease = null,
        private readonly ?\Closure $progress = null,
    ) {
        $this->elements = new \WeakMap();
    }

    private ?\SplObjectStorage $activeElements = null;

    /** An explicit active-document snapshot also permits hosted native fragment elements. */
    public function setActiveElements(?\SplObjectStorage $elements): void
    {
        if ($this->activeElements === $elements) {
            return;
        }
        $this->activeElements = $elements;
        foreach ($this->elements as $element => $running) {
            if (!$this->isActive($element)) {
                unset($this->elements[$element]);
            }
        }
    }

    private function isActive(\Dom\Element $element): bool
    {
        return null === $this->activeElements ? $element->isConnected : isset($this->activeElements[$element]);
    }

    public function __clone()
    {
        $this->elements = clone $this->elements;
    }

    public function adopt(self $trial): void
    {
        $this->elements = clone $trial->elements;
        $this->activeElements = $trial->activeElements;
    }

    public function apply(\Dom\Element $element, array $values, array $keyframes, float $time, array $importantProperties = [], array $startingValues = [], ?\Closure $scrollProgress = null, ?\Closure $transitionEnds = null, string $role = ''): array
    {
        if (!is_finite($time) || $time < 0) {
            throw new \InvalidArgumentException('Animation time must be finite and nonnegative.');
        }
        if (!$this->isActive($element)) {
            unset($this->elements[$element]);

            return $values;
        }
        if ('none' === ($values['display'] ?? 'inline')) {
            $this->forget($element, '' === $role ? null : $role);

            return $values;
        }
        $owner = $this->elements[$element] ?? [];
        $previous = '' === $role ? (isset($owner['targets']) ? $owner : null) : ($owner['roles'][$role] ?? null);
        if (null === $previous && $startingValues) {
            $previous = ['targets' => $startingValues, 'current' => $startingValues, 'transitions' => [], 'animations' => []];
        }
        $targets = $values;
        $transition = Transition::fromDeclarations($values);
        $transitions = $previous['transitions'] ?? [];
        $current = $previous['current'] ?? [];
        $layout = false;
        foreach ($transitions as $property => $running) {
            if (null === $transition?->of($property)) {
                unset($transitions[$property]);
                continue;
            }
            $current[$property] = $this->transitionValue($running, $time, $property);
        }
        if (null !== $previous) {
            foreach ($values as $property => $value) {
                if (str_starts_with($property, 'animation') || str_starts_with($property, 'transition') || str_starts_with($property, '--html-') || !isset($previous['targets'][$property]) || $previous['targets'][$property] === $value) {
                    continue;
                }
                $timing = $transition?->of($property);
                $from = $current[$property] ?? $previous['targets'][$property];
                $end = $value;
                $intrinsic = null !== $timing && \in_array($property, ['height', 'min-height', 'max-height', 'block-size', 'min-block-size', 'max-block-size'], true) && ('allow-keywords' === ($values['interpolate-size'] ?? '') && (SizeInterpolation::intrinsic($from) || SizeInterpolation::intrinsic($end)) || null !== SizeInterpolation::calculation($from) || null !== SizeInterpolation::calculation($end));
                if ($intrinsic) {
                    $layout = true;
                    if (null !== $transitionEnds) {
                        [$from, $end] = $transitionEnds($property, $from, $end, $values);
                    }
                }
                if (null === $timing || !$timing[3] && !($this->interpolable ?? Interpolation::isInterpolable(...))($from, $end) || \count($transitions) >= 256 && !isset($transitions[$property])) {
                    unset($transitions[$property]);
                    continue;
                }
                $transitions[$property] = ['from' => $from, 'to' => $end, 'start' => $time, 'duration' => $timing[0], 'delay' => $timing[1], 'timing' => $timing[2], 'layout' => $intrinsic];
            }
        }
        $animations = [];
        $applied = [];
        $scrollDriven = false;
        foreach (($this->definitions ?? Animation::listFromDeclarations(...))($values) as $name => $definition) {
            if (!isset($keyframes[$definition->name])) {
                continue;
            }
            $running = $previous['animations'][$name] ?? null;
            if (null === $running || !self::sameDefinition($running['definition'], $definition)) {
                $running = ['definition' => $definition, 'elapsed' => 0.0, 'time' => $time];
            } else {
                if (!$running['definition']->paused) {
                    $running['elapsed'] += max(0.0, $time - $running['time']);
                }
                $running['definition'] = $definition;
                $running['time'] = $time;
            }
            if ($definition->isScrollDriven()) {
                $scrollDriven = true;
                $fraction = null !== $scrollProgress ? $scrollProgress($definition) : null;
                $progress = null !== $fraction && is_finite($fraction) ? $definition->progressAt($fraction) : null;
            } else {
                $progress = $definition->progress($running['elapsed']);
            }
            if (null !== $progress) {
                $animated = $definition->valuesAt($keyframes[$definition->name], null !== $this->progress ? ($this->progress)($progress) : $progress, $values, $this->between ?? Interpolation::between(...), $this->add ?? Interpolation::add(...));
                foreach ($animated as $property => $value) {
                    if (!\in_array($property, $importantProperties, true)) {
                        $values[$property] = $value;
                        $applied[$property] = true;
                    }
                }
            }
            $animations[$name] = $running;
        }
        foreach ($transitions as $property => $running) {
            if ($time >= $running['start'] + $running['delay'] + $running['duration']) {
                unset($transitions[$property]);
                continue;
            }
            $values[$property] = $this->transitionValue($running, $time, $property);
            $applied[$property] = true;
        }
        $state = ['targets' => $targets, 'current' => $values, 'transitions' => $transitions, 'animations' => $animations, 'applied' => array_keys($applied), 'scroll' => $scrollDriven, 'layout' => $layout || array_any($transitions, static fn (array $running): bool => $running['layout'] ?? false)];

        if ('' === $role) {
            if (isset($owner['roles'])) {
                $state['roles'] = $owner['roles'];
            }
            $this->elements[$element] = $state;
        } else {
            $owner['roles'][$role] = $state;
            $this->elements[$element] = $owner;
        }

        return $values;
    }

    /** @return list<string> */
    public function getAppliedProperties(\Dom\Element $element, string $role = ''): array
    {
        $state = $this->elements[$element] ?? [];

        return $this->isActive($element) ? ('' === $role ? ($state['applied'] ?? []) : ($state['roles'][$role]['applied'] ?? [])) : [];
    }

    public function hasLayoutTransitions(): bool
    {
        foreach ($this->elements as $element => $state) {
            if ($this->isActive($element) && (($state['layout'] ?? false) || array_any($state['roles'] ?? [], static fn (array $role): bool => $role['layout'] ?? false))) {
                return true;
            }
        }

        return false;
    }

    public function hasScrollAnimation(\Dom\Element $element, string $role = ''): bool
    {
        $state = $this->elements[$element] ?? [];

        return $this->isActive($element) && ('' === $role ? ($state['scroll'] ?? false) : ($state['roles'][$role]['scroll'] ?? false));
    }

    public function hasScrollAnimations(): bool
    {
        foreach ($this->elements as $element => $state) {
            if ($this->isActive($element) && (($state['scroll'] ?? false) || array_any($state['roles'] ?? [], static fn (array $role): bool => $role['scroll'] ?? false))) {
                return true;
            }
        }

        return false;
    }

    public function forget(\Dom\Element $element, ?string $role = null): void
    {
        if (null === $role) {
            unset($this->elements[$element]);

            return;
        }
        $state = $this->elements[$element] ?? [];
        unset($state['roles'][$role]);
        if (empty($state['roles'])) {
            unset($state['roles']);
        }
        if ($state) {
            $this->elements[$element] = $state;
        } else {
            unset($this->elements[$element]);
        }
    }

    public function retainPseudoElements(\Dom\Element $element, array $roles): void
    {
        $wanted = array_fill_keys($roles, true);
        foreach (($this->elements[$element] ?? [])['roles'] ?? [] as $role => $_) {
            if (!isset($wanted[$role])) {
                $this->forget($element, $role);
            }
        }
    }

    public function hasPendingFrames(float $time): bool
    {
        foreach ($this->elements as $element => $state) {
            if (!$this->isActive($element)) {
                unset($this->elements[$element]);
                continue;
            }
            if (self::statePending($state, $time)) {
                return true;
            }
            foreach ($state['roles'] ?? [] as $role) {
                if (self::statePending($role, $time)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function statePending(array $state, float $time): bool
    {
        foreach ($state['transitions'] ?? [] as $transition) {
            if ($time < $transition['start'] + $transition['delay'] + $transition['duration']) {
                return true;
            }
        }
        foreach ($state['animations'] ?? [] as $running) {
            $definition = $running['definition'];
            if (!$definition->paused && !$definition->isScrollDriven() && $definition->isRunning($running['elapsed'] + max(0.0, $time - $running['time']))) {
                return true;
            }
        }

        return false;
    }

    private function transitionValue(array $transition, float $time, string $property): string
    {
        $progress = ($time - $transition['start'] - $transition['delay']) / $transition['duration'];
        if ($progress <= 0) {
            return $transition['from'];
        }
        if ($progress >= 1) {
            return $transition['to'];
        }

        return ($this->between ?? Interpolation::between(...))($transition['from'], $transition['to'], ($this->ease ?? Easing::evaluate(...))($transition['timing'], $progress), $property);
    }

    private static function sameDefinition(Animation $before, Animation $after): bool
    {
        $a = get_object_vars($before);
        $b = get_object_vars($after);
        unset($a['paused'], $b['paused']);

        return $a === $b;
    }
}
