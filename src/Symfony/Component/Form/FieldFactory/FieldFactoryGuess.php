<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\FieldFactory;

/**
 * Contains a value guessed by a FieldFactoryGuesserInterface instance
 *
 * Each instance also contains a confidence value about the correctness of
 * the guessed value. Thus an instance with confidence HIGH_CONFIDENCE is
 * more likely to contain a correct value than an instance with confidence
 * LOW_CONFIDENCE.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class FieldFactoryGuess
{
    /**
     * Marks an instance with a value that is very likely to be correct
     * @var integer
     */
    const HIGH_CONFIDENCE = 2;

    /**
     * Marks an instance with a value that is likely to be correct
     * @var integer
     */
    const MEDIUM_CONFIDENCE = 1;

    /**
     * Marks an instance with a value that may be correct
     * @var integer
     */
    const LOW_CONFIDENCE = 0;

    /**
     * The list of allowed confidence values
     * @var array
     */
    protected static $confidences = array(
        self::HIGH_CONFIDENCE,
        self::MEDIUM_CONFIDENCE,
        self::LOW_CONFIDENCE,
    );

    /**
     * The guessed value
     * @var mixed
     */
    protected $value;

    /**
     * The confidence about the correctness of the value
     *
     * One of HIGH_CONFIDENCE, MEDIUM_CONFIDENCE and LOW_CONFIDENCE.
     *
     * @var integer
     */
    protected $confidence;

    /**
     * Returns the guess most likely to be correct from a list of guesses
     *
     * If there are multiple guesses with the same, highest confidence, the
     * returned guess is any of them.
     *
     * @param  array $guesses     A list of guesses
     * @return FieldFactoryGuess  The guess with the highest confidence
     */
    static public function getBestGuess(array $guesses)
    {
        usort($guesses, function ($a, $b) {
            return $b->getConfidence() - $a->getConfidence();
        });

        return count($guesses) > 0 ? $guesses[0] : null;
    }

    /**
     * Constructor
     *
     * @param mixed $value          The guessed value
     * @param integer $confidence   The confidence
     */
    public function __construct($value, $confidence)
    {
        if (!in_array($confidence, self::$confidences)) {
            throw new \UnexpectedValueException(sprintf('The confidence should be one of "%s"', implode('", "', self::$confidences)));
        }

        $this->value = $value;
        $this->confidence = $confidence;
    }

    /**
     * Returns the guessed value
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns the confidence that the guessed value is correct
     *
     * @return integer  One of the constants HIGH_CONFIDENCE, MEDIUM_CONFIDENCE
     *                  and LOW_CONFIDENCE
     */
    public function getConfidence()
    {
        return $this->confidence;
    }
}