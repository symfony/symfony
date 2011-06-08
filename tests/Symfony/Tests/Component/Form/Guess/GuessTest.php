<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Guess;

use Symfony\Component\Form\Guess\Guess;

class TestGuess extends Guess {}

class GuessTest extends \PHPUnit_Framework_TestCase
{
    public function testGetBestGuessReturnsGuessWithHighestConfidence()
    {
        $guess1 = new TestGuess(Guess::MEDIUM_CONFIDENCE);
        $guess2 = new TestGuess(Guess::LOW_CONFIDENCE);
        $guess3 = new TestGuess(Guess::HIGH_CONFIDENCE);

        $this->assertSame($guess3, Guess::getBestGuess(array($guess1, $guess2, $guess3)));
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testGuessExpectsValidConfidence()
    {
        new TestGuess(5);
    }
}
