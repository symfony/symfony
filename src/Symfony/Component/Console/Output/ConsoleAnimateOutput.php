<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Terminal;

class ConsoleAnimateOutput extends StreamOutput
{
    private $content = [];
    private $lines = 0;
    private $terminal;
    private $slowDown;

    private const ANIMATE_LETTER_TIME = 10000;

    public function __construct($stream, int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = null, OutputFormatterInterface $formatter = null, int $slowDown = 0)
    {
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        $this->terminal = new Terminal();
        $this->slowDown = $slowDown;
    }

    public function clearScreen()
    {
        parent::doWrite($this->popStreamContentUntilCurrentSection($this->terminal->getHeight()), false);
    }

    /**
     * Clears previous output for this console
     *
     * @param int $lines Number of lines to clear. If null, then the entire output of this section is cleared
     */
    public function clear(int $lines = null)
    {
        if (empty($this->content) || !$this->isDecorated()) {
            return;
        }

        if ($lines) {
            array_splice($this->content, -($lines * 2)); // Multiply lines by 2 to cater for each new line added between content
        } else {
            $lines = $this->lines;
            $this->content = [];
        }

        $this->lines -= $lines;

        parent::doWrite($this->popStreamContentUntilCurrentSection($lines), false);

    }

    public function wait(float $time = 1)
    {
        usleep(1000000 * $time);
    }

    /**
     * Overwrites the previous output with a new message.
     *
     * @param array|string $message
     */
    public function overwriteln($message, ?int $slowDown = null)
    {
        $this->writeln('');
        $this->clear($this->lines);
        if (null !== $slowDown) {
            $this->setSlowDown($slowDown);
        }

        $this->writeln($message);
    }

    /**
     * Overwrites the previous output with a new message.
     *
     * @param array|string $message
     */
    public function overwrite($message, ?int $slowDown = null)
    {
        // Add new line to force clean current line
        $this->writeln('');
        $this->clear($this->lines);
        if (null !== $slowDown) {
            $this->setSlowDown($slowDown);
        }

        $this->write($message);
    }

    public function getContent(): string
    {
        return implode('', $this->content);
    }

    public function setSlowDown(int $slowDown) {
        $this->slowDown = $slowDown;
    }

    /**
     * @internal
     */
    public function addContent(string $input, bool $newline)
    {
        foreach (explode(PHP_EOL, $input) as $lineContent) {
            $this->content[] = $lineContent;
            if ($newline) {
                $this->lines += ceil($this->getDisplayLength($lineContent) / $this->terminal->getWidth()) ?: 1;
                $this->content[] = PHP_EOL;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite($message, $newline)
    {
        if (!$this->isDecorated()) {
            parent::doWrite($message, $newline);

            return;
        }

        $erasedContent = $this->popStreamContentUntilCurrentSection();

        $this->addContent($message, $newline);

        foreach (str_split($message) as $char) {
            parent::doWrite($char, false);
            usleep(self::ANIMATE_LETTER_TIME * $this->slowDown);
        }

        if ($newline) {
            parent::doWrite('', $newline);
        }

        parent::doWrite($erasedContent, false);
    }

    /**
     * At initial stage, cursor is at the end of stream output. This method makes cursor crawl upwards until it hits
     * current section. Then it erases content it crawled through. Optionally, it erases part of current section too.
     */
    private function popStreamContentUntilCurrentSection(int $numberOfLinesToClearFromCurrentSection = 0): string
    {
        $numberOfLinesToClear = $numberOfLinesToClearFromCurrentSection;
        $erasedContent = [];

        if ($numberOfLinesToClear > 0) {
            // move cursor up n lines
            parent::doWrite(sprintf("\x1b[%dA", $numberOfLinesToClear), false);
            // erase to end of screen
            parent::doWrite("\x1b[0J", false);
        }

        return implode('', array_reverse($erasedContent));
    }

    private function getDisplayLength(string $text): string
    {
        return Helper::strlenWithoutDecoration($this->getFormatter(), str_replace("\t", '        ', $text));
    }
}
