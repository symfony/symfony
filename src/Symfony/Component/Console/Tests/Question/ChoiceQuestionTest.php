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

use Symfony\Component\Console\Question\ChoiceQuestion;

class ChoiceQuestionTest extends \PHPUnit_Framework_TestCase
{
    public function testSetGetPrompt()
    {
        $question = new ChoiceQuestion('', array());
        $this->assertEquals(' > ', $question->getPrompt());

        $question->setPrompt('foo');
        $this->assertEquals('foo', $question->getPrompt());

        $question = new ChoiceQuestion('', array(), null, array('prompt' => 'bar'));
        $this->assertEquals('bar', $question->getPrompt());
    }
}
