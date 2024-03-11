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
    private array $places = [];
    private ?array $context = null;

    /**
     * @param int[] $representation Keys are the place name and values should be 1
     */
    public function __construct(array $representation = [])
    {
        foreach ($representation as $place => $nbToken) {
            $this->mark($place, $nbToken);
        }
    }

    /**
     * @return void
     */
    public function mark(string $place, int $nbToken = 1)
    {
        if ($nbToken < 1) {
            throw new \LogicException(sprintf('The number of tokens must be greater than 0, "%s" given.', $nbToken));
        }

        if (!\array_key_exists($place, $this->places)) {
            $this->places[$place] = 0;
        }
        $this->places[$place] += $nbToken;
    }

    /**
     * @return void
     */
    public function unmark(string $place, int $nbToken = 1)
    {
        if ($nbToken < 1) {
            throw new \LogicException(sprintf('The number of tokens must be greater than 0, "%s" given.', $nbToken));
        }

        if (!$this->has($place)) {
            throw new \LogicException(sprintf('The place "%s" is not marked.', $place));
        }

        $this->places[$place] -= $nbToken;

        if (0 > $this->places[$place]) {
            throw new \LogicException(sprintf('The place "%s" could not contain a negative token number.', $place));
        }

        if (0 === $this->places[$place]) {
            unset($this->places[$place]);
        }
    }

    /**
     * @return bool
     */
    public function has(string $place)
    {
        return \array_key_exists($place, $this->places);
    }

    /**
     * @return array
     */
    public function getPlaces()
    {
        return $this->places;
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
