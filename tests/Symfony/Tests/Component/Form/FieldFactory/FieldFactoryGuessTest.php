<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\FieldFactory;

use Symfony\Component\Form\FieldFactory\FieldFactoryGuess;

class FieldFactoryGuessTest extends \PHPUnit_Framework_TestCase
{
    public function testGetBestGuessReturnsGuessWithHighestConfidence()
    {
        $guess1 = new FieldFactoryGuess('foo', FieldFactoryGuess::MEDIUM_CONFIDENCE);
        $guess2 = new FieldFactoryGuess('bar', FieldFactoryGuess::LOW_CONFIDENCE);
        $guess3 = new FieldFactoryGuess('baz', FieldFactoryGuess::HIGH_CONFIDENCE);

        $this->assertEquals($guess3, FieldFactoryGuess::getBestGuess(array($guess1, $guess2, $guess3)));
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testGuessExpectsValidConfidence()
    {
        new FieldFactoryGuess('foo', 5);
    }
}