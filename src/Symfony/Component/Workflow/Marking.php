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
 * Marking contains the place of every tokens.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Marking
{
    /**
     * @var array<string, int>
     */
    private array $places = [];
    /**
     * @var array<string, \UnitEnum>
     */
    private array $enumPlaces = [];
    private ?array $context = null;

    /**
     * @param array<string, int> $representation Keys are the place name and values should be 1
     */
    public function __construct(array $representation = [])
    {
        foreach ($representation as $place => $nbToken) {
            $this->mark($place);
        }
    }

    public function mark(string|\UnitEnum $place): void
    {
        $key = $this->enumOrStringToKey($place);
        $this->places[$key] = 1;
        if ($place instanceof \UnitEnum) {
            $this->enumPlaces[$key] = $place;
        }
    }

    public function unmark(string|\UnitEnum $place): void
    {
        $key = $this->enumOrStringToKey($place);
        unset(
            $this->places[$key],
            $this->enumPlaces[$key]
        );
    }

    public function has(string|\UnitEnum $place): bool
    {
        return isset($this->places[$this->enumOrStringToKey($place)]);
    }

    /**
     * @return array<string, int>
     */
    public function getPlaces(): array
    {
        return $this->places;
    }

    /**
     * @return array<string, \UnitEnum>
     */
    public function getEnumPlaces(): array
    {
        return $this->enumPlaces;
    }

    /**
     * @internal
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Returns the context after the subject has transitioned.
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    private function enumOrStringToKey(string|\UnitEnum $place): string
    {
        if (is_string($place)) {
            return $place;
        }

        if ($place instanceof \BackedEnum) {
            return (string)$place->value;
        }

        return $place->name;
    }
}
