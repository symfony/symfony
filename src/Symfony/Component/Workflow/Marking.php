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
    private $places = [];

    /**
     * @param int[] $representation Keys are the place name and values should be 1
     */
    public function __construct(array $representation = [])
    {
        foreach ($representation as $place => $nbToken) {
            $this->mark($place);
        }
    }

    public function mark(string $place): void
    {
        $this->places[$place] = 1;
    }

    public function unmark(string $place): void
    {
        unset($this->places[$place]);
    }

    public function has(string $place): bool
    {
        return isset($this->places[$place]);
    }

    public function getPlaces(): array
    {
        return $this->places;
    }
}
