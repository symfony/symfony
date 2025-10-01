<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Transition
{
    /**
     * @var Arc[]
     */
    private array $fromArcs;

    /**
     * @var Arc[]
     */
    private array $toArcs;

    /**
     * @param string|string[]|Arc[] $froms
     * @param string|string[]|Arc[] $tos
     */
    public function __construct(
        private string $name,
        string|array $froms,
        string|array $tos,
    ) {
        $this->fromArcs = array_map($this->normalize(...), (array) $froms);
        $this->toArcs = array_map($this->normalize(...), (array) $tos);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return $asArc is true ? array<Arc> : array<string>
     */
    public function getFroms(/* bool $asArc = false */): array
    {
        if (1 <= \func_num_args() && func_get_arg(0)) {
            return $this->fromArcs;
        }

        return array_column($this->fromArcs, 'place');
    }

    /**
     * @return $asArc is true ? array<Arc> : array<string>
     */
    public function getTos(/* bool $asArc = false */): array
    {
        if (1 <= \func_num_args() && func_get_arg(0)) {
            return $this->toArcs;
        }

        return array_column($this->toArcs, 'place');
    }

    // No type hint for $arc to avoid implicit cast
    private function normalize(mixed $arc): Arc
    {
        if ($arc instanceof Arc) {
            return $arc;
        }

        if (\is_string($arc)) {
            return new Arc($arc, 1);
        }

        throw new \TypeError(\sprintf('The type of arc is invalid. Expected string or Arc, got "%s".', get_debug_type($arc)));
    }
}
