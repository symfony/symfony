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

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 * @author Gabriel Ostrolucký <gabriel.ostrolucky@gmail.com>
 */
class ConsoleSectionOutput extends StreamOutput
{
    private $content = [];
    private $lines = 0;
    private $sections;
    private $terminal;

    /**
     * @param resource               $stream
     * @param ConsoleSectionOutput[] $sections
     */
    public function __construct($stream, array &$sections, int $verbosity, bool $decorated, OutputFormatterInterface $formatter)
    {
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        array_unshift($sections, $this);
        $this->sections = &$sections;
        $this->terminal = new Terminal();
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

        if ($lines) {
            array_splice($this->content, -$lines);
        } else {
            $lines = $this->lines;
            $this->content = [];
        }

        $this->lines -= $lines;

        parent::doWrite($this->popStreamContentUntilCurrentSection($lines), false);
    }

    /**
     * Overwrites the previous output with a new message.
     *
     * @param array|string $message
     */
    public function overwrite($message)
    {
        $this->clear();
        $this->writeln($message);
    }

    public function getContent(): string
    {
        return implode('', $this->content);
    }

    /**
     * @internal
     */
    public function addContent(string $input, bool $newline = true)
    {
        $width = $this->terminal->getWidth();
        $lines = explode(\PHP_EOL, $input);
        $count = \count($lines) - 1;
        foreach ($lines as $i => $lineContent) {
            // re-add the line break (that has been removed in the above `explode()` for
            // - every line that is not the last line
            // - if $newline is required, also add it to the last line
            if ($i < $count || $newline) {
                $lineContent .= \PHP_EOL;
            }

            // skip line if there is no text (or newline for that matter)
            if ('' === $lineContent) {
                continue;
            }

            // For the first line, check if the previous line (last entry of `$this->content`)
            // needs to be continued (i.e. does not end with a line break).
            if (0 === $i
                && (false !== $lastLine = end($this->content))
                && !str_ends_with($lastLine, \PHP_EOL)
            ) {
                // deduct the line count of the previous line
                $this->lines -= (int) ceil($this->getDisplayLength($lastLine) / $width) ?: 1;
                // concatenate previous and new line
                $lineContent = $lastLine.$lineContent;
                // replace last entry of `$this->content` with the new expanded line
                array_splice($this->content, -1, 1, $lineContent);
            } else {
                // otherwise just add the new content
                $this->content[] = $lineContent;
            }

            $this->lines += (int) ceil($this->getDisplayLength($lineContent) / $width) ?: 1;
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

        // Check if the previous line (last entry of `$this->content`) needs to be continued
        // (i.e. does not end with a line break). In which case, it needs to be erased first.
        $deleteLastLine = ($lastLine = end($this->content) ?: '') && !str_ends_with($lastLine, \PHP_EOL) ? 1 : 0;
        $erasedContent = $this->popStreamContentUntilCurrentSection($deleteLastLine);

        $this->addContent($message, $newline);

        // If the last line was removed, re-print its content together with the new content.
        // Otherwise, just print the new content.
        parent::doWrite($deleteLastLine ? $lastLine.$message : $message, true);
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

        foreach ($this->sections as $section) {
            if ($section === $this) {
                break;
            }

            $numberOfLinesToClear += $section->lines;
            if ('' !== $sectionContent = $section->getContent()) {
                if (!str_ends_with($sectionContent, \PHP_EOL)) {
                    $sectionContent .= \PHP_EOL;
                }
                $erasedContent[] = $sectionContent;
            }
        }

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
