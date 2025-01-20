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
     * @param int[] $representation Keys are the place name and values should be superior or equals to 1
     */
    public function __construct(array $representation = [])
    {
        foreach ($representation as $place => $nbToken) {
            $this->mark($place, $nbToken);
        }
    }

    /**
     * @param int $nbToken
     *
     * @psalm-param int<1, max> $nbToken
     */
    public function mark(string $place /* , int $nbToken = 1 */): void
    {
        $nbToken = 1 < \func_num_args() ? func_get_arg(1) : 1;

        if ($nbToken < 1) {
            throw new \InvalidArgumentException(\sprintf('The number of tokens must be greater than 0, "%s" given.', $nbToken));
        }

        $this->places[$place] ??= 0;
        $this->places[$place] += $nbToken;
    }

    /**
     * @param int $nbToken
     *
     * @psalm-param int<1, max> $nbToken
     */
    public function unmark(string $place /* , int $nbToken = 1 */): void
    {
        $nbToken = 1 < \func_num_args() ? func_get_arg(1) : 1;

        if ($nbToken < 1) {
            throw new \InvalidArgumentException(\sprintf('The number of tokens must be greater than 0, "%s" given.', $nbToken));
        }

        if (!$this->has($place)) {
            throw new \InvalidArgumentException(\sprintf('The place "%s" is not marked.', $place));
        }

        $tokenCount = $this->places[$place] - $nbToken;

        if (0 > $tokenCount) {
            throw new \InvalidArgumentException(\sprintf('The place "%s" could not contain a negative token number: "%s" (initial) - "%s" (nbToken) = "%s".', $place, $this->places[$place], $nbToken, $tokenCount));
        }

        if (0 === $tokenCount) {
            unset($this->places[$place]);
        } else {
            $this->places[$place] = $tokenCount;
        }
    }

    public function has(string $place): bool
    {
        return isset($this->places[$place]);
    }

    public function getPlaces(): array
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
