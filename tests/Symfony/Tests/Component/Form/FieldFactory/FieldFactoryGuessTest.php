<?php

namespace Symfony\Tests\Component\Form\FieldFactory;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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