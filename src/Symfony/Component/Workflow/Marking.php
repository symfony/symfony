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

use Symfony\Component\Workflow\Utils\PlaceEnumerationUtils;

/**
 * Marking contains the place of every tokens.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Marking
{
    private array $places = [];
    private ?array $context = null;

    /**
     * @param int[]|\UnitEnum[] $representation Keys are the place name and values should be 1, unless UnitEnums
     *                                          are used as workflow places
     */
    public function __construct(array $representation = [])
    {
        foreach ($representation as $place => $token) {
            $this->mark($token instanceof \UnitEnum ? $token : $place);
        }
    }

    public function mark(string|\UnitEnum $place)
    {
        $this->places[PlaceEnumerationUtils::getPlaceKey($place)] = 1;
    }

    public function unmark(string|\UnitEnum $place)
    {
        unset($this->places[PlaceEnumerationUtils::getPlaceKey($place)]);
    }

    public function has(string|\UnitEnum $place)
    {
        return isset($this->places[PlaceEnumerationUtils::getPlaceKey($place)]);
    }

    public function getPlaces()
    {
        $places = [];
        foreach ($this->places as $key => $value) {
            $typedKey = PlaceEnumerationUtils::getTypedValue($key);
            if ($typedKey instanceof \UnitEnum) {
                $places[$key] = $typedKey;
            } else {
                $places[$typedKey] = 1;
            }
        }

        return $places;
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
}
