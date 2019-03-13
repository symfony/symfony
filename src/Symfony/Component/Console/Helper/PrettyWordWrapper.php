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

/**
 * @author Krisztián Ferenczi <ferenczi.krisztian@gmail.com>
 */
class WordWrapper
{
    // Defaults
    const DEFAULT_WIDTH = 120;
    const DEFAULT_BREAK = PHP_EOL;
    const DEFAULT_CUT = self::CUT_LONG_WORDS;

    // Cut options
    const CUT_DISABLE = 0;
    const CUT_LONG_WORDS = 1;
    const CUT_WORDS = 3; // Cut long words too
    const CUT_URLS = 4;
    const CUT_ALL = 7;

    const TAG_REGEX = '[a-z][a-z0-9,_=;-]*+';

    protected static $instance;

    /**
     * We collect the new lines into this array.
     *
     * @var array
     */
    protected $newLines;

    /**
     * The current line "words".
     *
     * @var array
     */
    protected $newLineTokens;

    /**
     * The current line "real" length, without the formatter "tags" and the spaces!
     *
     * @var int
     */
    protected $currentLength;

    protected static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $string     The text
     * @param int    $width      Character width of one line
     * @param string $break      The line breaking character(s)
     * @param int    $cutOptions You can mix your needs with CUT_* constants
     *
     * @return string
     */
    public static function wrap(string $string, int $width = self::DEFAULT_WIDTH, string $break = self::DEFAULT_BREAK, int $cutOptions = self::DEFAULT_CUT): string
    {
        $wrapper = self::getInstance();

        return $wrapper->wordwrap($string, $width, $break, $cutOptions);
    }

    /**
     * @param string $string     The text
     * @param int    $width      Character width of one line
     * @param string $break      The line breaking character(s)
     * @param int    $cutOptions You can mix your needs with CUT_* constants
     *
     * @return string
     */
    public function wordwrap(string $string, int $width = self::DEFAULT_WIDTH, string $break = self::DEFAULT_BREAK, int $cutOptions = self::DEFAULT_CUT): string
    {
        if ($width <= 0) {
            throw new \InvalidArgumentException('You have to set more than 0 width!');
        }
        if (0 == mb_strlen($break)) {
            throw new \InvalidArgumentException('You have to use existing end of the line character or string!');
        }
        $this->reset();
        $lines = explode($break, $string);
        foreach ($lines as $n => $line) {
            // Token can be a word
            foreach (explode(' ', $line) as $token) {
                $virtualTokenLength = $this->getVirtualTokenLength($token);
                $lineLength = $this->getCurrentLineLength();
                if ($lineLength + $virtualTokenLength < $width) {
                    $this->addTokenToLine($token, $virtualTokenLength);
                } else {
                    $this->handleLineEnding($token, $virtualTokenLength, $width, $cutOptions);
                }
            }
            $this->closeLine();
        }

        return $this->finish($break);
    }

    /**
     * This function handles what what does
     *
     * @param string $token
     * @param int    $virtualTokenLength
     * @param int    $width
     * @param int    $cutOptions
     */
    protected function handleLineEnding(string $token, int $virtualTokenLength, int $width, int $cutOptions)
    {
        switch (true) {
            // Token is an URL and we don't want to cut it
            case $this->tokenIsAnUrl($token) && !$this->hasCutOption(self::CUT_URLS, $cutOptions):
                $this->closeLine();
                $this->addTokenToLine($token, $virtualTokenLength);
                break;
            // We cut everything
            case $this->hasCutOption(self::CUT_WORDS, $cutOptions):
                $freeSpace = $width - $this->getCurrentLineLength() - 1;
                $this->sliceToken($token, $freeSpace, $width);
                break;
            // We want to cut the long words
            case $virtualTokenLength > $width && $this->hasCutOption(self::CUT_LONG_WORDS, $cutOptions):
                $freeSpace = $width - $this->getCurrentLineLength() - 1;
                // A little prettifying
                if ($freeSpace < 5 && $width > 10) {
                    $this->closeLine();
                    $freeSpace = $width;
                }
                $this->sliceToken($token, $freeSpace, $width);
                break;
            // Other situation...
            default:
                $this->closeLine();
                $this->addTokenToLine($token, $virtualTokenLength);
                break;
        }
    }

    /**
     * Close a line.
     */
    protected function closeLine()
    {
        if (\count($this->newLineTokens)) {
            $this->newLines[] = implode(' ', $this->newLineTokens);
            $this->newLineTokens = [];
            $this->currentLength = 0;
        }
    }

    /**
     * Register a token with set length.
     *
     * @param string $token
     * @param int    $virtualTokenLength
     */
    protected function addTokenToLine(string $token, int $virtualTokenLength)
    {
        $this->newLineTokens[] = $token;
        $this->currentLength += $virtualTokenLength;
    }

    /**
     * Close everything and build the formatted text.
     *
     * @param string $break
     *
     * @return string
     */
    protected function finish(string $break)
    {
        $this->closeLine();

        return implode($break, $this->newLines);
    }

    /**
     * Reset the array containers.
     */
    protected function reset()
    {
        $this->newLineTokens = [];
        $this->newLines = [];
    }

    /**
     * How long the current line is: currentLength + number of spaces (token numbers - 1).
     *
     * @return int
     */
    protected function getCurrentLineLength()
    {
        return $this->currentLength + \count($this->newLineTokens) - 1;
    }

    /**
     * Virtual token length = length without "formatter tags". Eg:
     *      - lorem --> 5
     *      - <comment>lorem</comment> --> 5.
     *
     * @param string $token
     *
     * @return int
     */
    protected function getVirtualTokenLength(string $token): int
    {
        $virtualTokenLength = mb_strlen($token);
        if (false !== strpos($token, '<')) {
            $untaggedToken = $this->pregReplaceTags('', $token);
            $virtualTokenLength = mb_strlen($untaggedToken);
        }

        return $virtualTokenLength;
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    protected function tokenIsAnUrl(string $token): bool
    {
        return false !== mb_strpos($token, 'http://') || false !== mb_strpos($token, 'https://');
    }

    protected function sliceToken(string $token, int $freeChars, int $width)
    {
        // We try to finds "formatter tags":
        // verylongword<comment>withtags</comment> --> verylongword <comment> withtags </comment>
        $tokenBlocks = explode(' ', $this->pregReplaceTags(' \\0 ', $token));
        $slicedToken = '';
        $slicedTokenVirtualLength = 0;
        foreach ($tokenBlocks as $block) {
            while ($block) {
                if ($freeChars <= 0) {
                    if ('' != $slicedToken) {
                        $this->addTokenToLine($slicedToken, $slicedTokenVirtualLength);
                    }
                    $this->closeLine();
                    $slicedToken = '';
                    $slicedTokenVirtualLength = 0;
                    $freeChars = $width;
                }
                list($partial, $block, $blockLength) = $this->sliceTokenBlock($block, $freeChars);
                $freeChars -= $blockLength;
                $slicedTokenVirtualLength += $blockLength;
                $slicedToken .= $partial;
            }
        }
        $this->addTokenToLine($slicedToken, $slicedTokenVirtualLength);
    }

    /**
     * It handles the long word "blocks".
     *
     * @param string $tokenBlock
     * @param int    $freeChars
     *
     * @return array [$token, $block, $blockLength]
     */
    protected function sliceTokenBlock(string $tokenBlock, int $freeChars): array
    {
        if ('<' == $tokenBlock[0] && '>' == mb_substr($tokenBlock, -1)) {
            return [$tokenBlock, '', 0];
        }
        $blockLength = mb_strlen($tokenBlock);
        if ($blockLength <= $freeChars) {
            return [$tokenBlock, '', $blockLength];
        }

        return [
            mb_substr($tokenBlock, 0, $freeChars),
            mb_substr($tokenBlock, $freeChars),
            $freeChars,
        ];
    }

    /**
     * It checks the cut option is set. See the CUT_* constants.
     *
     * @param int $option
     * @param int $cutOptions
     *
     * @return bool
     */
    protected function hasCutOption(int $option, int $cutOptions): bool
    {
        return ($cutOptions & $option) === $option;
    }

    /**
     * It replaces all tags to something different. If you want to use original tags, use the `\\0` placeholder:
     *
     * Eg:
     *      $replacement = 'STARTTAG>\\0<ENDTAG'
     *                          ^^^ placeholder
     *      $string = '<comment>Test comment</comment>'
     *      return: 'STARTTAG><comment><ENDTAGTest commentSTARTTAG></comment><ENDTAG'
     *
     * All placeholders:
     *      \\0: <comment> and </comment>
     *      \\1: comment and /comment or / (!: the close tag could be `</>`)
     *      \\2: comment or '' (if the close tag is `</>`)
     *
     * @param string $replacement
     * @param string $string
     *
     * @return string
     */
    protected function pregReplaceTags(string $replacement, string $string): string
    {
        return preg_replace(
            sprintf('{<((%1$s)|/(%1$s)?)>}', self::TAG_REGEX),
            $replacement,
            $string
        );
    }
}
