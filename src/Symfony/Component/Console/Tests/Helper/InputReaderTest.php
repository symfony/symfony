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

use Symfony\Component\Console\Helper\InputReader;

/**
 * @group tty
 *
 * @author Janusz Jablonski <januszjablonski.pl@gmail.com>
 */
class InputReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\Console\Helper\InputReader
     */
    private $reader;
    private $stream;
    private $output;

    protected function setUp()
    {
        $this->stream = fopen('php://memory','r+');
        $this->reader = new InputReader($this->stream);
        $this->output = $this->getMock(
            'Symfony\\Component\\Console\\Output\\OutputInterface'
        );
    }

    public function testReadDataFromStream()
    {
        $this->streamWrite("test message 1");
        $result = $this->reader->read($this->output);
        $this->assertEquals("test message 1", $result);
    }

    public function testRemoveChars()
    {
        $this->streamWrite("message to remove" . InputReader::BACKSPACE . InputReader::BACKSPACE);
        $result = $this->reader->read($this->output);
        $this->assertEquals("message to remo", $result);
    }

    public function testRemoveMoreCharThenItIsPossible()
    {
        $this->streamWrite("short" . str_repeat(InputReader::ARROW_LEFT, 3) . str_repeat(InputReader::BACKSPACE, 4));
        $result = $this->reader->read($this->output);
        $this->assertEquals("ort", $result);
    }

    public function testPressEnter()
    {
        $this->streamWrite("press " . InputReader::FORM_FEED . " enter");
        $result = $this->reader->read($this->output);
        $this->assertEquals("press ", $result);
    }

    public function testMoveCursorAndAddChars()
    {
        $this->streamWrite("add" . InputReader::ARROW_LEFT . "some"  . InputReader::ARROW_RIGHT .  "text");
        $result = $this->reader->read($this->output);
        $this->assertEquals("adsomedtext", $result);
    }

    public function testMoveCursorAndDeleteChars()
    {
        $this->streamWrite("delete char" . str_repeat(InputReader::ARROW_LEFT, 2) . InputReader::DELETE);
        $result = $this->reader->read($this->output);
        $this->assertEquals("delete chr", $result);
    }

    public function testIgnoreUpAndDownArrows()
    {
        $this->streamWrite("ignore" . InputReader::ARROW_UP . "arrows" . InputReader::ARROW_DOWN);
        $result = $this->reader->read($this->output);
        $this->assertEquals("ignorearrows", $result);
    }

    private function streamWrite($value)
    {
        fwrite($this->stream, $value);
        rewind($this->stream);
    }
}
