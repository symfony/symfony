<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Css;

/** @internal A bounded prepared-cascade frame with one final timeline commit. */
final class LayoutStyleTrial
{
    private readonly object $owner;
    private bool $committed = false;

    public function __construct(private ?array $initial, private ?\Closure $resolve, private ?\Closure $commit)
    {
        $this->owner = new \stdClass();
        $this->initial['layout-trial-owner'] = $this->owner;
    }

    public function getInitial(): array
    {
        return $this->initial ?? throw new \LogicException('The layout style trial has been disposed.');
    }

    public function resolve(\SplObjectStorage $metrics): array
    {
        if (null === $this->resolve || $this->committed) {
            throw new \LogicException('A completed layout style trial cannot resolve another candidate.');
        }
        $candidate = ($this->resolve)($metrics);
        $candidate['layout-trial-owner'] = $this->owner;

        return $candidate;
    }

    public function commit(array $candidate): void
    {
        if (null === $this->commit || $this->committed || ($candidate['layout-trial-owner'] ?? null) !== $this->owner) {
            throw new \LogicException('A layout style candidate must belong to this uncommitted frame.');
        }
        $this->committed = true;
        ($this->commit)($candidate);
    }

    public function dispose(): void
    {
        $this->initial = null;
        $this->resolve = $this->commit = null;
    }
}
