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

use Symfony\Component\Console\Question\ChoicesCursor;

class ChoicesCursorTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $choicesCursor = new ChoicesCursor(4);

        $this->assertEquals(3, $choicesCursor->getPosition());
        $this->assertFalse($choicesCursor->hasMoved());
    }

    public function testMoveAtUp()
    {
        $choicesCursor = new ChoicesCursor(4, 3);
        $diff = $choicesCursor->moveAt(1);

        $this->assertEquals(2, $diff);
        $this->assertEquals(1, $choicesCursor->getPosition());
        $this->assertTrue($choicesCursor->hasMoved());
    }

    public function testMoveAtDown()
    {
        $choicesCursor = new ChoicesCursor(4, 0);
        $diff = $choicesCursor->moveAt(3);

        $this->assertEquals(-3, $diff);
        $this->assertEquals(3, $choicesCursor->getPosition());
        $this->assertTrue($choicesCursor->hasMoved());
    }

    public function testMoveUp()
    {
        $choicesCursor = new ChoicesCursor(4, 3);
        $diff = $choicesCursor->moveUp();

        $this->assertEquals(1, $diff);
        $this->assertEquals(2, $choicesCursor->getPosition());
        $this->assertTrue($choicesCursor->hasMoved());
    }

    public function testMoveUpTop()
    {
        $choicesCursor = new ChoicesCursor(4, 0);
        $diff = $choicesCursor->moveUp();

        $this->assertEquals(-3, $diff);
        $this->assertEquals(3, $choicesCursor->getPosition());
        $this->assertTrue($choicesCursor->hasMoved());
    }

    public function testMoveDown()
    {
        $choicesCursor = new ChoicesCursor(4, 0);
        $diff = $choicesCursor->moveDown();

        $this->assertEquals(-1, $diff);
        $this->assertEquals(1, $choicesCursor->getPosition());
        $this->assertTrue($choicesCursor->hasMoved());
    }

    public function testMoveDownBottom()
    {
        $choicesCursor = new ChoicesCursor(4, 3);
        $diff = $choicesCursor->moveDown();

        $this->assertEquals(3, $diff);
        $this->assertEquals(0, $choicesCursor->getPosition());
        $this->assertTrue($choicesCursor->hasMoved());
    }
}
