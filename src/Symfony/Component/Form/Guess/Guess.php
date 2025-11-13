<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Guess;

use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * Base class for guesses made by FormTypeGuesserInterface implementation.
 *
 * Each instance contains a confidence value about the correctness of the guess.
 * Thus an instance with confidence HIGH_CONFIDENCE is more likely to be
 * correct than an instance with confidence LOW_CONFIDENCE.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Guess
{
    /**
     * Marks an instance with a value that is extremely likely to be correct.
     */
    public const VERY_HIGH_CONFIDENCE = 3;

    /**
     * Marks an instance with a value that is very likely to be correct.
     */
    public const HIGH_CONFIDENCE = 2;

    /**
     * Marks an instance with a value that is likely to be correct.
     */
    public const MEDIUM_CONFIDENCE = 1;

    /**
     * Marks an instance with a value that may be correct.
     */
    public const LOW_CONFIDENCE = 0;

    /**
     * The confidence about the correctness of the value.
     *
     * One of VERY_HIGH_CONFIDENCE, HIGH_CONFIDENCE, MEDIUM_CONFIDENCE
     * and LOW_CONFIDENCE.
     */
    private int $confidence;

    /**
     * Returns the guess most likely to be correct from a list of guesses.
     *
     * If there are multiple guesses with the same, highest confidence, the
     * returned guess is any of them.
     *
     * @param static[] $guesses An array of guesses
     */
    public static function getBestGuess(array $guesses): ?static
    {
        $result = null;
        $maxConfidence = -1;

        foreach ($guesses as $guess) {
            if ($maxConfidence < $confidence = $guess->getConfidence()) {
                $maxConfidence = $confidence;
                $result = $guess;
            }
        }

        return $result;
    }

    /**
     * @throws InvalidArgumentException if the given value of confidence is unknown
     */
    public function __construct(int $confidence)
    {
        if (self::VERY_HIGH_CONFIDENCE !== $confidence && self::HIGH_CONFIDENCE !== $confidence
            && self::MEDIUM_CONFIDENCE !== $confidence && self::LOW_CONFIDENCE !== $confidence) {
            throw new InvalidArgumentException('The confidence should be one of the constants defined in Guess.');
        }

        $this->confidence = $confidence;
    }

    /**
     * Returns the confidence that the guessed value is correct.
     *
     * @return int One of the constants VERY_HIGH_CONFIDENCE, HIGH_CONFIDENCE,
     *             MEDIUM_CONFIDENCE and LOW_CONFIDENCE
     */
    public function getConfidence(): int
    {
        return $this->confidence;
    }
}
