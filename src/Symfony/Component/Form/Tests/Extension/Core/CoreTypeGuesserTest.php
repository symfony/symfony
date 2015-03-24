<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core;

use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Extension\Core\CoreTypeGuesser;

class CoreTypeGuesserTest extends \PHPUnit_Framework_TestCase
{
    /** @var CoreTypeGuesser */
    private $guesser;

    const AUTHOR_FIXTURE = 'Symfony\Component\Form\Tests\Fixtures\Author';
    const PROPERTIES_DEFINED_FIXTURE = 'Symfony\Component\Form\Tests\Fixtures\SubmitResetPropertyFixture';

    protected function setUp()
    {
        $this->guesser = new CoreTypeGuesser();
    }

    public function testButtonTypeInferral()
    {
        $submitGuess = $this->guesser->guessType(self::AUTHOR_FIXTURE, 'submit');
        $resetGuess = $this->guesser->guessType(self::AUTHOR_FIXTURE, 'reset');

        $this->assertInstanceOf('Symfony\Component\Form\Guess\TypeGuess', $submitGuess);
        $this->assertEquals('submit', $submitGuess->getType());
        $this->assertEquals(Guess::LOW_CONFIDENCE, $submitGuess->getConfidence());

        $this->assertInstanceOf('Symfony\Component\Form\Guess\TypeGuess', $resetGuess);
        $this->assertEquals('reset', $resetGuess->getType());
        $this->assertEquals(Guess::LOW_CONFIDENCE, $resetGuess->getConfidence());
    }

    public function testButtonPropertyExists()
    {
        $submitGuess = $this->guesser->guessType(self::PROPERTIES_DEFINED_FIXTURE, 'submit');
        $resetGuess = $this->guesser->guessType(self::PROPERTIES_DEFINED_FIXTURE, 'reset');

        $this->assertNull($submitGuess);
        $this->assertNull($resetGuess);
    }
}
