<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Style;

/**
 * This class can help you to create well formatted text blocks with "tags", like: <comment>, <info>, <question>.
 */
class WordWrapper
{
    /**
     * How many characters one line can contain.
     *
     * @var int
     */
    protected $width;

    /**
     * End of the lines.
     *
     * @var string
     */
    protected $break;

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

    public function __construct($width, $break)
    {
        if ($width <= 0) {
            throw new \InvalidArgumentException('You have to set more than 0 width!');
        }
        if (0 == mb_strlen($break)) {
            throw new \InvalidArgumentException('You have to use existing end of the line character or string!');
        }
        $this->width = $width;
        $this->break = $break;
    }

    /**
     * Close a line.
     */
    protected function closeLine()
    {
        if (\count($this->newLineTokens)) {
            $this->newLines[] = implode(' ', $this->newLineTokens);
            $this->newLineTokens = array();
            $this->currentLength = 0;
        }
    }

    /**
     * Register a token with setted length.
     *
     * @param string $token
     * @param int    $virtualTokenLength
     */
    protected function addTokenToLine($token, $virtualTokenLength)
    {
        $this->newLineTokens[] = $token;
        $this->currentLength += $virtualTokenLength;
    }

    /**
     * Close everything and build the formatted text.
     *
     * @return string
     */
    protected function finish()
    {
        $this->closeLine();

        return implode($this->break, $this->newLines);
    }

    /**
     * Reset the array containers.
     */
    protected function reset()
    {
        $this->newLineTokens = array();
        $this->newLines = array();
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
     * @param $token
     *
     * @return int
     */
    protected function getVirtualTokenLength($token)
    {
        $virtualTokenLength = mb_strlen($token);
        if (false !== strpos($token, '<')) {
            $untaggedToken = preg_replace('/<[^>]+>/', '', $token);
            $virtualTokenLength = mb_strlen($untaggedToken);
        }

        return $virtualTokenLength;
    }

    /**
     * @param string $string       The text
     * @param bool   $cutLongWords How the function handles the too long words that is longer then a line. It ignores
     *                             this settings if the word is an URL!
     *
     * @return string
     */
    public function formattedStringWordwrap($string, $cutLongWords = true)
    {
        $this->reset();
        $lines = explode($this->break, $string);
        foreach ($lines as $n => $line) {
            // Token can be a word
            foreach (explode(' ', $line) as $token) {
                $virtualTokenLength = $this->getVirtualTokenLength($token);
                $lineLength = $this->getCurrentLineLength();
                // If width would be greater with the new token/word. The count()-1 is the number of spaces!
                if ($lineLength + $virtualTokenLength < $this->width) {
                    $this->addTokenToLine($token, $virtualTokenLength);
                } else {
                    if ($virtualTokenLength < $this->width) {
                        $this->closeLine();
                        $this->addTokenToLine($token, $virtualTokenLength);
                    } elseif (!$cutLongWords || 'http' == mb_substr($token, 0, 4)) {
                        // We don't cat the long word if the $catLongWords is false or the word is an URL
                        $this->closeLine();
                        $this->addTokenToLine($token, $virtualTokenLength);
                        $this->closeLine();
                    } else {
                        $this->handleLongToken($token);
                    }
                }
            }
            $this->closeLine();
        }

        return $this->finish();
    }

    /**
     * If the word is longer than how long one line can be.
     *
     * @param string $token
     */
    protected function handleLongToken($token)
    {
        $freeChars = $this->width - ($this->getCurrentLineLength() + 1);
        // We start a new line if there is less space than 5 characters.
        if ($freeChars < 5) {
            $this->closeLine();
            $freeChars = $this->width;
        }
        // We try to finds "formatter tags":
        // verylongword<comment>withtags</comment> --> verylongword <comment> withtags </comment>
        $tokenBlocks = explode(' ', preg_replace('/<[^>]+>/', ' \\0 ', $token));
        $slicedToken = '';
        $slicedTokenVirtualLength = 0;
        foreach ($tokenBlocks as $block) {
            while ($block) {
                list($token, $block, $blockLength) = $this->sliceTokenBlock($block, $freeChars);
                $freeChars -= $blockLength;
                $slicedTokenVirtualLength += $blockLength;
                $slicedToken .= $token;
                if (!$freeChars) {
                    $this->addTokenToLine($slicedToken, $slicedTokenVirtualLength);
                    $this->closeLine();
                    $slicedToken = '';
                    $slicedTokenVirtualLength = 0;
                    $freeChars = $this->width;
                }
            }
        }
        $this->addTokenToLine($slicedToken, $slicedTokenVirtualLength);
    }

    /**
     * It handles the long word "blocks".
     *
     * @param $tokenBlock
     * @param $freeChars
     *
     * @return array [$token, $block, $blockLength]
     */
    protected function sliceTokenBlock($tokenBlock, $freeChars)
    {
        if ('<' == $tokenBlock[0] && '>' == mb_substr($tokenBlock, -1)) {
            return array($tokenBlock, '', 0);
        }
        $blockLength = mb_strlen($tokenBlock);
        if ($blockLength <= $freeChars) {
            return array($tokenBlock, '', $blockLength);
        }

        return array(
            mb_substr($tokenBlock, 0, $freeChars),
            mb_substr($tokenBlock, $freeChars),
            $freeChars,
        );
    }
}
