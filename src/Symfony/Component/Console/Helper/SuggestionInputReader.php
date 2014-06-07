<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Janusz Jablonski <januszjablonski.pl@gmail.com>
 */
class SuggestionInputReader extends InputReader
{
    protected $suggestions = array();
    protected $wrapBegin = '{';
    protected $wrapEnd = '}';

    private $suggestPosition = -1;
    private $lastPosition = 0;
    private $filtered = array();
    private $filteredInput = '';

    /**
     * @param array $list
     */
    public function setSuggestions(array $list)
    {
        $this->suggestions = $list;
    }

    /**
     * @param string $begin
     * @param string $end
     */
    public function setWraps($begin, $end)
    {
        $this->wrapBegin = $begin;
        $this->wrapEnd = $end;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return string
     */
    public function read(OutputInterface $output)
    {
        $this->filtered = $this->suggestions;
        $this->suggestPosition = -1;
        $this->lastPosition = 0;
        $this->filteredInput = '';

        return parent::read($output);
    }

    /**
     * @param string $inputChunk
     *
     * @return int
     */
    protected function parseStream($inputChunk)
    {
        if (
            $inputChunk[0] === self::FORM_FEED
            && isset($this->filtered[$this->suggestPosition])
            && $this->input != $this->filtered[$this->suggestPosition]
        ) {
            $this->confirm();
            $parentUsed = 0;
        } else {
            $parentUsed = parent::parseStream($inputChunk);
        }
        if (0 === $this->lastPosition  && 0 < $this->position) {
            $this->suggestPosition = 0;
        } elseif (0 === $this->position && 0 < $this->lastPosition) {
            $this->suggestPosition = -1;
        }
        $this->lastPosition = $this->position;
        $used = 1;
        if (-1 !== $this->suggestPosition) {
            $this->filterSuggestion();
        }
        if ($inputChunk[0] === self::TAB) {
            $this->confirm();
            $used = 1;
        } elseif (0 === strncmp($inputChunk, self::ARROW_RIGHT, 3)) {
            if (mb_strlen($this->input) <= $this->position) {
                $this->confirm();
            }
            $used = 3;
        } elseif (0 === strncmp($inputChunk, self::ARROW_DOWN, 3)) {
            if (-1 === $this->suggestPosition) {
                $this->filterSuggestion();
            }
            $this->suggestPosition++;
            if (count($this->filtered) <= $this->suggestPosition) {
                $this->suggestPosition = 0;
            }
            $used = 3;
        } elseif (0 === strncmp($inputChunk, self::ARROW_UP, 3)) {
            if (-1 === $this->suggestPosition) {
                $this->filterSuggestion();
                $this->suggestPosition = 0;
            }
            $this->suggestPosition--;
            if (0 > $this->suggestPosition) {
                $this->suggestPosition = count($this->filtered) - 1;
            }
            $used = 3;
        } elseif ($inputChunk[0] === self::ESCAPE) {
            $this->suggestPosition = -1;
            $used = 1;
        }
        if (isset($this->filtered[$this->suggestPosition])) {
            $suffix = mb_substr(
                $this->filtered[$this->suggestPosition],
                mb_strlen($this->input)
            );
            $this->print = $this->input . $this->wrapBegin . $suffix . $this->wrapEnd;
        }

        return max($used, $parentUsed);
    }

    private function confirm()
    {
        if (isset($this->filtered[$this->suggestPosition])) {
            $this->print = $this->input = $this->filtered[$this->suggestPosition];
            $this->position = mb_strlen($this->input);
        }
    }

    private function filterSuggestion()
    {
        if (isset($this->filtered[$this->suggestPosition])) {
            $current =  $this->filtered[$this->suggestPosition];
        } else {
            $current = "";
        }
        if ('' === $this->input) {
            $this->filtered = $this->suggestions;
        } elseif ($this->input !== $this->filteredInput) {
            if (0 < strcmp($this->filteredInput, $this->input)) {
                $suggestion = $this->suggestions;
            } else {
                $suggestion = $this->filtered;
            }
            $matches = array();
            foreach ($suggestion as $item) {
                if (0 === strpos($item, $this->input)) {
                    $matches[] = $item;
                }
            }
            $this->filtered = $matches;
            $this->filteredInput = $this->input;
        }
        if ("" != $current) {
            $key = array_search($current, $this->filtered);
            $this->suggestPosition = $key;
        }
    }

}
