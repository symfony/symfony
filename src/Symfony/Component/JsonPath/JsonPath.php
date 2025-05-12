<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @immutable
 *
 * @experimental
 */
final class JsonPath
{
    /**
     * @param non-empty-string $path
     */
    public function __construct(
        private readonly string $path = '$',
    ) {
    }

    public function key(string $key): static
    {
        return new self($this->path.(str_ends_with($this->path, '..') ? '' : '.').$key);
    }

    public function index(int $index): static
    {
        return new self($this->path.'['.$index.']');
    }

    public function deepScan(): static
    {
        return new self($this->path.'..');
    }

    public function all(): static
    {
        return new self($this->path.'[*]');
    }

    public function first(): static
    {
        return new self($this->path.'[0]');
    }

    public function last(): static
    {
        return new self($this->path.'[-1]');
    }

    public function slice(int $start, ?int $end = null, ?int $step = null): static
    {
        $slice = $start;
        if (null !== $end) {
            $slice .= ':'.$end;
            if (null !== $step) {
                $slice .= ':'.$step;
            }
        }

        return new self($this->path.'['.$slice.']');
    }

    public function filter(string $expression): static
    {
        return new self($this->path.'[?('.$expression.')]');
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
