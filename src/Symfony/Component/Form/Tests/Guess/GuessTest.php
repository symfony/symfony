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
use Symfony\Component\Form\Guess\AbstractGuess;

class TestGuess extends AbstractGuess
{
}

class GuessTest extends TestCase
{
    public function testGetBestGuessReturnsGuessWithHighestConfidence()
    {
        $guess1 = new TestGuess(AbstractGuess::MEDIUM_CONFIDENCE);
        $guess2 = new TestGuess(AbstractGuess::LOW_CONFIDENCE);
        $guess3 = new TestGuess(AbstractGuess::HIGH_CONFIDENCE);

        $this->assertSame($guess3, AbstractGuess::getBestGuess([$guess1, $guess2, $guess3]));
    }

    public function testGuessExpectsValidConfidence()
    {
        $this->expectException(\InvalidArgumentException::class);
        new TestGuess(5);
    }
}
