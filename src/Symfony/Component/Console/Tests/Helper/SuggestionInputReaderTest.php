<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use Symfony\Component\Console\Helper\SuggestionInputReader;
use Symfony\Component\Console\Helper\InputReader;

/**
 * @group tty
 *
 * @author Janusz Jablonski <januszjablonski.pl@gmail.com>
 */
class SuggestionInputReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\Console\Helper\SuggestionInputReader
     */
    private $reader;
    private $stream;
    private $output;

    protected function setUp()
    {
        $this->stream = fopen('php://memory','r+');
        $this->reader = new SuggestionInputReader($this->stream);
        $this->output = $this->getMock(
            'Symfony\\Component\\Console\\Output\\OutputInterface'
        );
    }

    public function testConfirmSuggestionByTab()
    {
        $this->reader->setSuggestions(array('test'));
        $this->streamWrite("t" . InputReader::TAB);
        $result = $this->reader->read($this->output);
        $this->assertEquals("test", $result);
    }

    public function testTabNotWorkWhenInputIsEmpty()
    {
        $this->reader->setSuggestions(array('test'));
        $this->streamWrite("" . InputReader::TAB);
        $result = $this->reader->read($this->output);
        $this->assertEquals("", $result);
    }

    public function testConfirmSuggestionByRightArrow()
    {
        $this->reader->setSuggestions(array('test'));
        $this->streamWrite("t" . InputReader::ARROW_RIGHT);
        $result = $this->reader->read($this->output);
        $this->assertEquals("test", $result);
    }

    public function testDisableConfirmByArrowWhenPositionIsNotInTheEnd()
    {
        $this->reader->setSuggestions(array('someSuggestion'));
        $this->streamWrite(
            "some"
            . InputReader::ARROW_LEFT . InputReader::ARROW_LEFT
            . InputReader::ARROW_RIGHT
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("some", $result);
    }

    public function testConfirmByEnter()
    {
        $this->reader->setSuggestions(array('someSuggestion'));
        $this->streamWrite(
            "some"
            . InputReader::FORM_FEED
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("someSuggestion", $result);
    }

    public function testFindingCorrectSuggestionInList()
    {
        $this->reader->setSuggestions(array('abcd', 'efghi'));
        $this->streamWrite("ef" . InputReader::TAB);
        $result = $this->reader->read($this->output);
        $this->assertEquals("efghi", $result);
    }

    public function testIgnoreTabWhenListHasNoMatch()
    {
        $this->reader->setSuggestions(array('first', 'second'));
        $this->streamWrite("no" . InputReader::TAB);
        $result = $this->reader->read($this->output);
        $this->assertEquals("no", $result);
    }

    public function testSelectingUsingArrows()
    {
        $this->reader->setSuggestions(array('data1', 'data2', 'data3'));
        $this->streamWrite(
            "data"
            . InputReader::ARROW_DOWN . InputReader::ARROW_DOWN
            . InputReader::ARROW_UP
            . InputReader::TAB
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("data2", $result);
    }

    public function testGotoLastPositionAfterFirst()
    {
        $this->reader->setSuggestions(array('data1', 'data2', 'data3'));
        $this->streamWrite(
            "data"
            . InputReader::ARROW_UP
            . InputReader::TAB
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("data3", $result);

    }

    public function testGotoFistPositionAfterLast()
    {
        $this->reader->setSuggestions(array('data1', 'data2', 'data3'));
        $this->streamWrite(
            "data"
            . str_repeat(InputReader::ARROW_DOWN, 3)
            . InputReader::TAB
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("data1", $result);

    }

    public function testResetFilteredListWhenCharWillBeRemoved()
    {
        $this->reader->setSuggestions(array('dat1', 'dat2', 'data1', 'data2'));
        $this->streamWrite(
            "data"
            . InputReader::BACKSPACE
            . InputReader::ARROW_UP
            . InputReader::TAB
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("dat2", $result);
    }

    public function testEscPermanentDisableSuggestion()
    {
        $this->reader->setSuggestions(array('data1', 'data2', 'data3'));
        $this->streamWrite(
            "dat"
            . InputReader::ESCAPE
            . InputReader::BACKSPACE
            . InputReader::TAB
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("da", $result);
    }

    public function testArrowUpEnableDisabledSuggestion()
    {
        $this->reader->setSuggestions(array('data1', 'data2', 'data3'));
        $this->streamWrite(
            "dat"
            . InputReader::ESCAPE
            . InputReader::ARROW_UP
            . InputReader::TAB
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("data3", $result);
    }

    public function testArrowDownEnableDisabledSuggestion()
    {
        $this->reader->setSuggestions(array('data1', 'data2', 'data3'));
        $this->streamWrite(
            "dat"
            . InputReader::ESCAPE
            . InputReader::ARROW_DOWN
            . InputReader::TAB
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("data1", $result);
    }

    public function testSameSuggestionAfterFiltering()
    {
        $this->reader->setSuggestions(array('aabb', 'abb', 'aacc'));
        $this->streamWrite(
            "a"
            . InputReader::ARROW_UP
            . "a"
            . InputReader::TAB
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("aacc", $result);
    }

    public function testArrowUpActivateSuggestionEvenIfInputIsEmpty()
    {
        $this->reader->setSuggestions(array('aabb', 'abb', 'aacc'));
        $this->streamWrite(
            InputReader::ARROW_UP
            . InputReader::ARROW_UP
            . InputReader::TAB
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("abb", $result);
    }

    public function testArrowDownActivateSuggestionEvenIfInputIsEmpty()
    {
        $this->reader->setSuggestions(array('aabb', 'abb', 'aacc'));
        $this->streamWrite(
            InputReader::ARROW_DOWN
            . InputReader::TAB
        );
        $result = $this->reader->read($this->output);
        $this->assertEquals("aabb", $result);
    }

    private function streamWrite($value)
    {
        fwrite($this->stream, $value);
        rewind($this->stream);
    }

}
