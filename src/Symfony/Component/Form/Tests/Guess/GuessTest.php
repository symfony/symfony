<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Guess;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Guess\Guess;

class TestGuess extends Guess
{
}

class GuessTest extends TestCase
{
    public function testGetBestGuessReturnsGuessWithHighestConfidence()
    {
        $guess1 = new TestGuess(Guess::MEDIUM_CONFIDENCE);
        $guess2 = new TestGuess(Guess::LOW_CONFIDENCE);
        $guess3 = new TestGuess(Guess::HIGH_CONFIDENCE);

        $this->assertSame($guess3, Guess::getBestGuess([$guess1, $guess2, $guess3]));
    }

    public function testGuessExpectsValidConfidence()
    {
        $this->expectException('\InvalidArgumentException');
        new TestGuess(5);
    }
}
