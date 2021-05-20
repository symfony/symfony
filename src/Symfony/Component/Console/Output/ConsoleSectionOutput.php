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
 * @author Arne Groskurth <arne.groskurth@gmail.com>
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
     * Number of *dirty* lines above the terminal that need to be cleared when they reappear as the terminal gets resized.
     *
     * @var int
     */
    private $dirtyLines = 0;

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
        $this->terminal::registerResizeListener([$this, 'flushDirtyLines']);
    }

    public function __destruct()
    {
        $this->terminal::unregisterResizeListener([$this, 'flushDirtyLines']);
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

        // also triggers flushing of dirty lines in lower sections
        $terminalHeight = $this->terminal->getHeight();

        $lines = ($lines === null) ? $this->lines : min($lines, $this->lines);
        $linesToClear = $this->dirtyLines + $lines;
        $visibleLinesToClear = min($linesToClear, $this->getVisibleLines($terminalHeight));

        array_splice($this->content, -$lines);
        $this->lines -= $lines;
        $this->dirtyLines = $linesToClear - $visibleLinesToClear;

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

        // make sure a non-empty message ends with a newline
        if ($message !== '' && substr($message, -1) !== PHP_EOL) {
            $message .= PHP_EOL;
        }

        $terminalWidth = null;

        if ($this->isDecorated()) {

            // also triggers flushing of dirty lines in lower sections
            [$terminalWidth, $terminalHeight] = $this->terminal->getDimensions();

            $displayableLines = $this->getDisplayableLines($terminalHeight);

            // only overwrite section if it is visible
            if ($displayableLines > 0) {

                [, $newVisibleContent, $newVisibleLines] = $this->divideMessageByDisplayability($message, $terminalWidth, $terminalHeight);

                $flushableLines = min($this->dirtyLines, $displayableLines - $newVisibleLines);

                // only overwrite visible portion if it differs from what is already visible
                if ($flushableLines > 0 || substr($this->getContent(), -strlen($newVisibleContent)) !== $newVisibleContent) {

                    $erasedContent = $this->popStreamContentUntilCurrentSection(max($flushableLines, $this->getVisibleLines($terminalHeight)));

                    parent::doWrite($newVisibleContent, false);
                    parent::doWrite($erasedContent, false);

                    $this->dirtyLines -= $flushableLines;
                }
            }
        } else {
            // output is not decorated
            parent::doWrite($message, true);
        }

        $this->content = [];
        $this->lines = 0;
        $this->addContent($message, $terminalWidth);
    }

    public function getContent(): string
    {
        return implode('', $this->content);
    }

    /**
     * Tries to flush dirty lines after the terminal has been resized.
     *
     * @internal
     */
    public function flushDirtyLines(array $dimensions): void
    {
        [, $terminalHeight] = $dimensions;

        // flush all sections, starting with the bottom-most
        // flushing all sections at once to be independent of order this function is called among the sections
        $remainingHeight = $terminalHeight;
        foreach ($this->sections as $section) {

            if ($remainingHeight <= 0) {
                // top of terminal reached - no further flushes possible
                break;
            }

            if ($section->dirtyLines !== 0) {
                $section->overwrite($section->getContent());
            }

            $remainingHeight -= $section->getVisibleLines($terminalHeight);
        }
    }

    /**
     * Register content to the section that is e.g. back-printed to the terminal on user-input.
     *
     * @internal
     */
    public function addContent(string $input, ?int $terminalWidth = null)
    {
        $terminalWidth = $terminalWidth ?? $this->terminal->getWidth();

        foreach (explode(PHP_EOL, $input) as $lineContent) {
            $lineContent .= PHP_EOL;
            $this->lines += $this->getDisplayHeight($lineContent, $terminalWidth);
            $this->content[] = $lineContent;
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

        // also triggers flushing of dirty lines in lower sections
        [$terminalWidth, $terminalHeight] = $this->terminal->getDimensions();

        $isLastSection = $this === $this->sections[0];

        // only write anything to the terminal if section is visible
        if (!$isLastSection && $this->getDisplayableLines($terminalHeight) > 0) {

            $erasedContent = $this->popStreamContentUntilCurrentSection();

            $newSectionContent = $this->getContent() . $message;
            [, $visibleContent] = $this->divideMessageByDisplayability($newSectionContent, $terminalWidth, $terminalHeight);

            parent::doWrite($visibleContent, true);
            parent::doWrite($erasedContent, false);
        }

        $this->addContent($message, $terminalWidth);

        // just append to the terminal if this is the last section
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
    private function divideMessageByDisplayability(string $text, int $terminalWidth, int $terminalHeight): array
    {
        $portion1 = '';
        $portion2 = $text;
        $portion2Height = $this->getDisplayHeight($portion2, $terminalWidth);
        $verticalSpace = $this->getDisplayableLines($terminalHeight);

        // todo: implement binary search
        while ($portion2 !== '' && $portion2Height > $verticalSpace) {
            $portion1 .= substr($portion2, 0, 1);
            $portion2 = substr($portion2, 1);
            $portion2Height = $this->getDisplayHeight($portion2, $terminalWidth);
        }

        return [
            $portion1,
            $portion2,
            $portion2Height,
        ];
    }

    private function getDisplayHeight(string $text, int $terminalWidth): int
    {
        return substr_count($text, PHP_EOL) + floor($this->getDisplayLength($text) / $terminalWidth);
    }

    private function getDisplayLength(string $text): string
    {
        return Helper::strlenWithoutDecoration($this->getFormatter(), str_replace("\t", '        ', $text));
    }

    /**
     * Returns the number of lines (including dirty lines) that are visible with the current terminal size.
     */
    private function getVisibleLines(int $terminalHeight): int
    {
        return min($this->getDisplayableLines($terminalHeight), $this->lines + $this->dirtyLines);
    }

    /**
     * Returns the number of lines that this section can use with the current terminal size.
     */
    private function getDisplayableLines(int $terminalHeight): int
    {
        $remainingHeight = $terminalHeight;

        foreach ($this->sections as $section) {

            if ($section === $this || $remainingHeight <= 0) {
                break;
            }

            $remainingHeight -= $section->lines;
        }

        return max($remainingHeight, 0);
    }
}
