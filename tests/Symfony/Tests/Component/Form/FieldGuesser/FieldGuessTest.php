<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\FieldGuesser;

use Symfony\Component\Form\FieldGuesser\FieldGuess;

class FieldGuessTest extends \PHPUnit_Framework_TestCase
{
    public function testGetBestGuessReturnsGuessWithHighestConfidence()
    {
        $guess1 = new FieldGuess('foo', FieldGuess::MEDIUM_CONFIDENCE);
        $guess2 = new FieldGuess('bar', FieldGuess::LOW_CONFIDENCE);
        $guess3 = new FieldGuess('baz', FieldGuess::HIGH_CONFIDENCE);

        $this->assertEquals($guess3, FieldGuess::getBestGuess(array($guess1, $guess2, $guess3)));
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testGuessExpectsValidConfidence()
    {
        new FieldGuess('foo', 5);
    }
}