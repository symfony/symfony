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

use function strlen;

use const PHP_EOL;

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 * @author Gabriel Ostrolucký <gabriel.ostrolucky@gmail.com>
 */
class ConsoleSectionOutput extends StreamOutput
{
    /**
     * Current contents of this section as array of messages.
     *
     * @var string[]
     */
    private $content = [];

    /**
     * Number of lines currently occupied by this section.
     * Can be more then length of content array as a message can span over multiple lines.
     *
     * @var int
     */
    private $lines = 0;

    /**
     * All sections associated with the current console output.
     * The array is ordered from bottom-most to top-most section on screen.
     *
     * @var self[]
     */
    private $sections;

    /**
     * Provides information about the terminal.
     *
     * @var Terminal
     */
    private $terminal;

    /**
     * @param resource               $stream
     * @param ConsoleSectionOutput[] $sections
     */
    public function __construct($stream, array &$sections, int $verbosity, bool $decorated, OutputFormatterInterface $formatter, ?Terminal $terminal = null)
    {
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        array_unshift($sections, $this);
        $this->sections = &$sections;
        $this->terminal = $terminal ?? new Terminal();
    }

    /**
     * Clears previous output for this section.
     *
     * @param int $lines Number of lines to clear. If null, then the entire output of this section is cleared
     */
    public function clear(int $lines = null)
    {
        if (empty($this->content) || !$this->isDecorated()) {
            return;
        }

        Terminal::updateDimensions();

        $visibleLinesToClear = min($lines ?? $this->lines, $this->getVisibleLines());

        if ($lines) {
            array_splice($this->content, -$lines);
        } else {
            $lines = $this->lines;
            $this->content = [];
        }

        $this->lines -= $lines;

        if ($visibleLinesToClear > 0) {
            parent::doWrite($this->popStreamContentUntilCurrentSection($visibleLinesToClear), false);
        }
    }

    /**
     * Overwrites the previous output with a new message.
     *
     * @param string[]|string $message
     */
    public function overwrite($message)
    {
        if (is_iterable($message)) {
            $message = implode('', $message);
        }

        Terminal::updateDimensions();

        // only overwrite section if it is visible
        if ($this->getDisplayableLines() > 0) {

            [, $visibleContent, $visibleLines] = $this->divideMessageByDisplayability($message);
            $visibleContent .= PHP_EOL;

            // only overwrite visible portion if it differs from what is already visible
            if (substr($this->getContent(), -strlen($visibleContent)) !== $visibleContent) {

                $erasedContent = $this->popStreamContentUntilCurrentSection(max($visibleLines, $this->getVisibleLines()));

                parent::doWrite($visibleContent, false);
                parent::doWrite($erasedContent, false);
            }
        }

        $this->content = [];
        $this->lines = 0;
        $this->addContent($message);
    }

    public function getContent(): string
    {
        return implode('', $this->content);
    }

    /**
     * Register content to the section that is e.g. back-printed to the terminal on user-input.
     *
     * @internal
     */
    public function addContent(string $input)
    {
        foreach (explode(PHP_EOL, $input) as $lineContent) {
            $this->lines += $this->getDisplayHeight($lineContent);
            $this->content[] = $lineContent. PHP_EOL;
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

        Terminal::updateDimensions();

        $isLastSection = $this === $this->sections[0];

        // only write anything to the terminal if it is visible
        if (!$isLastSection && $this->getDisplayableLines() > 0) {

            $erasedContent = $this->popStreamContentUntilCurrentSection();

            $newSectionContent = $this->getContent() . $message;
            [, $visibleContent] = $this->divideMessageByDisplayability($newSectionContent);

            parent::doWrite($visibleContent, true);
            parent::doWrite($erasedContent, false);
        }

        $this->addContent($message);

        // just append to the terminal
        if ($isLastSection) {
            parent::doWrite($message, true);
        }
    }

    /**
     * At initial stage, cursor is at the end of stream output. This method makes cursor crawl upwards until it hits
     * current section. Then it erases content it crawled through. Optionally, it erases part of current section too.
     */
    private function popStreamContentUntilCurrentSection(int $numberOfLinesToClearFromCurrentSection = 0): string
    {
        $numberOfLinesToClear = $numberOfLinesToClearFromCurrentSection;
        $erasedContent = [];

        foreach ($this->sections as $section) {
            if ($section === $this) {
                break;
            }

            $numberOfLinesToClear += $section->lines;
            $erasedContent[] = $section->getContent();
        }

        if ($numberOfLinesToClear > 0) {
            // move cursor up n lines
            parent::doWrite(sprintf("\x1b[%dA", $numberOfLinesToClear), false);
            // erase to end of screen
            parent::doWrite("\x1b[0J", false);
        }

        return implode('', array_reverse($erasedContent));
    }

    /**
     * Divides the given message into two parts based on their visibility on the terminal when using as section content.
     *
     * The returned array has three items:
     * [0] (string) Portion of the text above the terminal
     * [1] (string) Portion of text on the terminal
     * [2] (int) Number of lines the visible portion of the text takes
     */
    private function divideMessageByDisplayability(string $text): array
    {
        $portion1 = '';
        $portion2 = $text;
        $portion2Height = $this->getDisplayHeight($portion2);
        $verticalSpace = $this->getDisplayableLines();

        // todo: can probably be implemented more efficiently (probably logarithmic in text length instead of linear)
        while ($portion2 !== '' && $portion2Height > $verticalSpace) {
            $portion1 .= substr($portion2, 0, 1);
            $portion2 = substr($portion2, 1);
            $portion2Height = $this->getDisplayHeight($portion2);
        }

        return [
            $portion1,
            $portion2,
            $portion2Height,
        ];
    }

    private function getDisplayHeight(string $text): int
    {
        return substr_count($text, PHP_EOL) + ceil($this->getDisplayLength($text) / $this->terminal->getWidth()) ?: 1;
    }

    private function getDisplayLength(string $text): string
    {
        return Helper::strlenWithoutDecoration($this->getFormatter(), str_replace("\t", '        ', $text));
    }

    /**
     * Returns the number of lines of this section that are visible with the current terminal size.
     *
     * @return int
     */
    private function getVisibleLines(): int
    {
        return min($this->getDisplayableLines(), $this->lines);
    }

    /**
     * Returns the number of lines that this section can use with the current terminal size.
     *
     * @return int
     */
    private function getDisplayableLines(): int
    {
        $remainingHeight = $this->terminal->getHeight();

        foreach ($this->sections as $section) {

            if ($section === $this || $remainingHeight <= 0) {
                break;
            }

            $remainingHeight -= $section->lines;
        }

        return max($remainingHeight, 0);
    }
}
