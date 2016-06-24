<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Question;

use Symfony\Component\Console\Question\Question;

class QuestionTest extends \PHPUnit_Framework_TestCase
{
    private $question;

    protected function setUp()
    {
        $this->question = new Question('Favorite framework?');
    }

    public function testGetSetHiddenFallback()
    {
        $this->assertTrue($this->question->isHiddenFallback());
        $this->question->setHiddenFallback(false);
        $this->assertFalse($this->question->isHiddenFallback());
    }

    /**
     * @expectedException \Symfony\Component\Console\Exception\LogicException
     * @expectedExceptionMessage A hidden question cannot use the autocompleter.
     */
    public function testHiddenWithAutocompleterValuesThrowsLogicException()
    {
        $this->question->setHidden(true);
        $this->question->setAutocompleterValues(array('symfony'));
    }

    /**
     * @expectedException \Symfony\Component\Console\Exception\LogicException
     * @expectedExceptionMessage A hidden question cannot use the autocompleter.
     */
    public function testAutocompleterValuesWithHiddenThrowsLogicException()
    {
        $this->question->setAutocompleterValues(array('symfony'));
        $this->question->setHidden(true);
    }

    /**
     * @expectedException \Symfony\Component\Console\Exception\InvalidArgumentException
     * @expectedExceptionMessage Autocompleter values can be either an array, `null` or an object implementing both `Countable` and `Traversable` interfaces.
     */
    public function testSetAutocompleterValuesThrowsInvalidArgumentException()
    {
        $this->question->setAutocompleterValues('symfony');
    }

    /**
     * @expectedException \Symfony\Component\Console\Exception\InvalidArgumentException
     * @expectedExceptionMessage Maximum number of attempts must be a positive value.
     */
    public function testSetMaxAttemptsThrowsInvalidArgumentException()
    {
        $this->question->setMaxAttempts(-1);
    }
}
