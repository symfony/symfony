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
 * It helps you to wrap long text with pretty breaks and useful cuts. You can control the cuts with the control option:
 *      - CUT_DISABLE:          Always break the text at word boundary.
 *      - CUT_LONG_WORDS:       If the word is longer than one row it will be cut.
 *      - CUT_WORDS:            Always break at set length, it will cut all words. It would be useful if you have little
 *                              space. (Info: It "contains" the CUT_LONG_WORDS option)
 *      - CUT_URLS:             Lots of terminal can recognize URL-s in text and make them clickable (if there isn't break
 *                              inside the URL) The URLS can be long, default we keep it in one block even if it gets ugly
 *                              response. You can switch this behavior off with this option. The result will be pretty,
 *                              but the URL won't be clickable.
 *      - CUT_FILL_UP_MISSING:  The program will fill up the rows with spaces in order to every row will be same long.
 *      - CUT_NO_REPLACE_EOL:   The program will replace the PHP_EOL in the input string to $break.
 *
 * <code>
 *      $message = "<comment>This is a comment message with <info>info</info></comment> ...";
 *      // Default:
 *      $output->writeln(PrettyWordWrapper::wrap($message, 120);
 *      // Use custom settings:
 *      $output->writeln(PrettyWordWrapper::wrap(
 *          $message,
 *          20,
 *          PrettyWordWrapper::CUT_ALL | PrettyWordWrap::CUT_FILL_UP_MISSING,
 *          PHP_EOL
 *      );
 * </code>
 *
 * Known problems, limitations:
 *      - You can't call PrettyWordWrapper::wrap() inside a "running wrap" because there are "cache" properties and
 *          it causes problems within a Singleton class. Solution: you can create a PrettyWordWrapper object, and
 *          use the $wrapper->wordwrap() non-static method.
 *      - If you use escaped tags AND (the line width is too short OR you use the CUT_WORDS option): `\<error>Message\</error>`,
 *          the wrapper could wrap inside the tag:
 *
 *              \<error>Me
 *              ssage\</er
 *              ror>
 *
 *          In this case maybe the OutputFormatter won't remove the second `\` character, because the wrapper broke the
 *          tag also, so it will shown like this:
 *
 *              <error>Me
 *              ssage\</er
 *              ror>
 *
 * @author Krisztián Ferenczi <ferenczi.krisztian@gmail.com>
 */
class PrettyWordWrapperHelper extends Helper
{
    // Defaults
    /** @var int */
    const DEFAULT_WIDTH = 120;
    /** @var string */
    const DEFAULT_BREAK = "\n";
    /** @var int */
    const DEFAULT_CUT = self::CUT_LONG_WORDS;

    // Cut options
    const CUT_DISABLE = 0;
    const CUT_LONG_WORDS = 1;
    const CUT_WORDS = 3; // Cut long words too
    const CUT_URLS = 4;
    const CUT_ALL = 7;
    const CUT_FILL_UP_MISSING = 8;
    const CUT_NO_REPLACE_EOL = 16;

    /**
     * This is a ZERO_WIDTH_SPACE UTF-8 character. It is used when we try to protect the escaped tags, eg: `\<error>`.
     *
     * @see https://en.wikipedia.org/wiki/Zero-width_space
     * @see https://www.fileformat.info/info/unicode/char/200b/index.htm
     */
    const ESCAPE_PROTECTION_CHAR = "\u{200B}";

    /**
     * @var self
     */
    protected static $instance;

    /**
     * "Cache".
     *
     * @var int|null
     */
    protected $width;

    /**
     * "Cache". Here you can use the CUT_* constants.
     *
     * @var int|null
     */
    protected $cutOption;

    /**
     * "Cache".
     *
     * @var string
     */
    protected $break;

    /**
     * "Cache". We collect the new lines into this array.
     *
     * @var array
     */
    protected $newLines;

    /**
     * "Cache". The current line "words".
     *
     * @var array
     */
    protected $newLineTokens;

    /**
     * "Cache". The current line "real" length, without the formatter "tags" and the spaces!
     *
     * @var int
     */
    protected $currentLength;

    /**
     * "Singleton.", but it isn't forbidden to create new objects, if you want.
     *
     * @return PrettyWordWrapperHelper
     */
    protected static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'prettywordwrapper';
    }

    /**
     * @param string $string    The text
     * @param int    $width     Character width of one line
     * @param int    $cutOption You can mix your needs with CUT_* constants
     * @param string $break     The line breaking character(s)
     *
     * @return string
     */
    public static function wrap(string $string, int $width = self::DEFAULT_WIDTH, int $cutOption = self::DEFAULT_CUT, string $break = self::DEFAULT_BREAK): string
    {
        $wrapper = self::getInstance();

        return $wrapper->wordwrap($string, $width, $cutOption, $break);
    }

    /**
     * @param string $string     The text
     * @param int    $width      Character width of one line
     * @param int    $cutOptions You can mix your needs with CUT_* constants
     * @param string $break      The line breaking character(s)
     *
     * @return string
     */
    public function wordwrap(string $string, int $width = self::DEFAULT_WIDTH, int $cutOptions = self::DEFAULT_CUT, string $break = self::DEFAULT_BREAK): string
    {
        // If the "cache" properties are in use, the program throw an exception, because you mustn't wrap inside a wrap.
        // If you need this, you can create a new wrapper object and you have to use this.
        if (null !== $this->width) {
            throw new \LogicException('You mustn\'t wrap inside a wrap!');
        }
        if ($width <= 0) {
            throw new \InvalidArgumentException('You have to set more than 0 width!');
        }
        if (0 == mb_strlen($break)) {
            throw new \InvalidArgumentException('You have to use existing end of the line character or string!');
        }
        // Reset all cache properties.
        $this->reset($width, $cutOptions, $break);
        // Unifies the line endings
        if (!$this->hasCutOption(self::CUT_NO_REPLACE_EOL)) {
            $string = str_replace(PHP_EOL, "\n", $string);
            $string = str_replace("\r\n", "\n", $string);
            $string = str_replace("\n\r", "\n", $string);
            $string = str_replace("\r", "\n", $string);
        }
        // Protect the escaped characters and tags.
        $string = $this->escape($string);
        // Slice string by break string
        $lines = explode($break, $string);
        foreach ($lines as $n => $line) {
            // Token can be a word
            foreach (explode(' ', $line) as $token) {
                $virtualTokenLength = $this->getVirtualTokenLength($token);
                $lineLength = $this->getCurrentLineLength();
                if ($lineLength + $virtualTokenLength < $width) {
                    $this->addTokenToLine($token, $virtualTokenLength);
                } else {
                    $this->handleLineEnding($token, $virtualTokenLength);
                }
            }
            $this->closeLine();
        }

        return $this->finish();
    }

    /**
     * This function handles what to happen at end of the line.
     *
     * @param string $token
     * @param int    $virtualTokenLength
     */
    protected function handleLineEnding(string $token, int $virtualTokenLength): void
    {
        switch (true) {
            // Token is an URL and we don't want to cut it
            case $this->tokenIsAnUrl($token) && !$this->hasCutOption(self::CUT_URLS):
                $this->closeLine();
                $this->addTokenToLine($token, $virtualTokenLength);
                break;
            // We cut everything
            case $this->hasCutOption(self::CUT_WORDS):
                $this->sliceToken($token);
                break;
            // We want to cut the long words
            case $virtualTokenLength > $this->width && $this->hasCutOption(self::CUT_LONG_WORDS):
                // A little prettifying, avoid like this:
                //      Lorem ipsum ve
                //      rylongtext dol
                //      or sit amet
                // With this:
                //      Lorem ipsum
                //      verylongtext
                //      dolor sit amet
                if ($this->getFreeSpace() < 5 && $this->width > 10) {
                    $this->closeLine();
                }
                $this->sliceToken($token);
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
    protected function closeLine(): void
    {
        if (\count($this->newLineTokens)) {
            // If the last word is empty, and there are other words, we drop it.
            if (\count($this->newLineTokens) > 1 && '' == $this->newLineTokens[\count($this->newLineTokens) - 1]) {
                array_pop($this->newLineTokens);
            }
            $line = $this->unescape(trim(implode(' ', $this->newLineTokens)));
            // Fill up the ends if necessary
            if ($this->hasCutOption(self::CUT_FILL_UP_MISSING)) {
                $line .= str_repeat(' ', max($this->getFreeSpace(), 0));
            }
            // Add current line and reset "line caches".
            $this->newLines[] = $line;
            $this->newLineTokens = [];
            $this->currentLength = 0;
        }
    }

    /**
     * Register a token with set length. We will implode them with ' ' - space -, that is why the $appendToLast could
     * be important if we adding a part of longer word.
     *
     *      $appendToLast = false
     *      <error>Error</error>  --> <error> Error </error>
     *
     *      $appendToLast = true
     *      <error>Error</error>  --> <error>Error</error>
     *
     * @param string $token
     * @param int    $virtualTokenLength
     * @param bool   $appendToLast       we set it true if we slice a longer word with tags eg
     */
    protected function addTokenToLine(string $token, int $virtualTokenLength, bool $appendToLast = false): void
    {
        if ($appendToLast) {
            $last = \count($this->newLineTokens) > 0
                ? array_pop($this->newLineTokens)
                : '';
            $token = $last.$token;
        }
        $this->newLineTokens[] = $token;
        $this->currentLength += $virtualTokenLength;
    }

    /**
     * We try to protect every escaped characters, especially the escaped formatting tags:.
     *
     *      \<error> --> [ZERO_WIDTH_SPACE]\<[ZERO_WIDTH_SPACE]error>
     *
     * In this form the tag regular expression won't find this as a tag.
     *
     * !!! PAY ATTANTION !!! Don't use the mb_* functions with preg_match() position answers. preg_match() gets "bytes",
     * not characters!
     *
     * @param string $string
     *
     * @return string
     */
    protected function escape(string $string): string
    {
        $output = '';
        $offset = 0;
        // The OFFSET value will be in byte!!!! Don't use mb_* functions when you use these numbers!
        preg_match_all('{\\\\<}u', $string, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $i => $match) {
            $pos = $match[1];
            $protectedBlock = $match[0];
            // Don't use the mb_* function here!!!
            $output .= substr($string, $offset, $pos - $offset)
                .self::ESCAPE_PROTECTION_CHAR
                .$protectedBlock
                .self::ESCAPE_PROTECTION_CHAR;
            // Don't use the mb_* function here!!!
            $offset = $pos + \strlen($protectedBlock);
        }
        // Don't use the mb_* function here!!!
        $output .= substr($string, $offset);

        return $output;
    }

    protected function unescape(string $string): string
    {
        return preg_replace('{'.self::ESCAPE_PROTECTION_CHAR.'(\\\\<)'.self::ESCAPE_PROTECTION_CHAR.'}u', '\\1', $string);
    }

    /**
     * Close everything and build the formatted text.
     *
     * @return string
     */
    protected function finish(): string
    {
        $this->closeLine();
        $fullEscapedText = implode($this->break, $this->newLines);
        $unescaped = $this->unescape($fullEscapedText);

        // reset "caches"
        $this->width = null;
        $this->cutOption = null;
        $this->break = null;

        return $unescaped;
    }

    /**
     * Reset and set properties.
     *
     * @param int    $width
     * @param int    $cutOptions
     * @param string $break
     */
    protected function reset(int $width, int $cutOptions, string $break): void
    {
        $this->width = $width;
        $this->cutOption = $cutOptions;
        $this->break = $break;
        $this->newLineTokens = [];
        $this->newLines = [];
    }

    /**
     * How long the current line is: currentLength + number of spaces (token numbers - 1).
     *
     * @return int
     */
    protected function getCurrentLineLength(): int
    {
        return $this->currentLength + \count($this->newLineTokens) - 1;
    }

    protected function getFreeSpace(): int
    {
        return $this->width - $this->getCurrentLineLength();
    }

    /**
     * Virtual token length = length without "formatter tags". Eg:
     *      - lorem --> 5
     *      - <comment>lorem</comment> --> 5
     *      - \<comment>lorem\</comment> --> 24 // We removed the two \ escaping character!
     *
     * @param string $token
     *
     * @return int
     */
    protected function getVirtualTokenLength(string $token): int
    {
        $virtualTokenLength = mb_strlen($token);
        if (false !== strpos($token, '<') || false !== strpos($token, self::ESCAPE_PROTECTION_CHAR)) {
            $untaggedToken = $this->pregReplaceTags('', $token);
            $unescapedToken = $this->unescape($untaggedToken);
            // Remove escaped tags
            $virtualTokenLength = mb_strlen($unescapedToken) - substr_count($unescapedToken, '\\<');
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

    /**
     * Slice a long token.
     *
     * !!! PAY ATTENTION !!! Don't use the mb_* functions with preg_match() position answers. preg_match() gets "bytes",
     * not characters!
     *
     * @param string $token
     */
    protected function sliceToken(string $token): void
    {
        if ($this->getFreeSpace() <= 0) {
            $this->closeLine();
        }
        $offset = 0;
        preg_match_all(Helper::getFormatTagRegexPattern(), $token, $matches, PREG_OFFSET_CAPTURE);
        // Init: append to this... See addTokenToLine(), $appendToLast parameter
        $this->addTokenToLine('', 0);
        foreach ($matches[0] as $i => $match) {
            $pos = $match[1];
            $tag = $match[0];

            // Add the text up to the next tag.
            // Don't replace it to mb_* function!
            $block = \substr($token, $offset, $pos - $offset);
            $offset = $pos + \strlen($tag);

            $this->breakLongToken($block);
            if (0 == $this->getFreeSpace() && '/' != $tag[1]) {
                $this->closeLine();
            }
            $this->addTokenToLine($tag, 0, true);
        }
        // Don't replace it to mb_* function!
        $block = \substr($token, $offset);
        if ($block) {
            $this->breakLongToken($block);
        }
    }

    protected function breakLongToken(string $token): void
    {
        $freeChars = $this->getFreeSpace();
        $token = $this->unescape($token);
        $prefix = \mb_substr($token, 0, $freeChars);
        $this->addTokenToLine($prefix, \mb_strlen($prefix), true);
        $tokenLength = \mb_strlen($token);
        for ($offset = $freeChars; $offset < $tokenLength; $offset += $this->width) {
            $subLength = min($this->width, $tokenLength - $offset);
            $subToken = \mb_substr($token, $offset, $subLength);
            if ($subLength + $this->getCurrentLineLength() > $this->width) {
                $this->closeLine();
            }
            $this->addTokenToLine($subToken, \mb_strlen($subToken), true);
        }
    }

    /**
     * It checks the cut option is set. See the CUT_* constants.
     *
     * @param int $option
     *
     * @return bool
     */
    protected function hasCutOption(int $option): bool
    {
        return ($this->cutOption & $option) === $option;
    }

    /**
     * It replaces all tags to something different. If you want to use original tags, use the `\\0` placeholder:.
     *
     * Eg:
     *      $replacement = 'STARTTAG>\\0<ENDTAG'
     *                               ^^^ placeholder
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
            Helper::getFormatTagRegexPattern(),
            $replacement,
            $string
        );
    }
}
