<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Utf8;

use Symfony\Component\Utf8\Exception\InvalidArgumentException;

/**
 * @internal
 */
trait Utf8Trait
{
    private static $commonCaseFold = array(
        array('µ', 'ſ', "\xCD\x85", 'ς', "\xCF\x90", "\xCF\x91", "\xCF\x95", "\xCF\x96", "\xCF\xB0", "\xCF\xB1", "\xCF\xB5", "\xE1\xBA\x9B", "\xE1\xBE\xBE"),
        array('μ', 's', 'ι',       'σ', 'β',       'θ',       'φ',       'π',       'κ',       'ρ',       'ε',       "\xE1\xB9\xA1", 'ι'),
    );

    private $string = '';

    public static function fromString(string $string)
    {
        if (!preg_match('//u', $string)) {
            throw new InvalidArgumentException('Given string is not a valid UTF-8 encoded string.');
        }

        $instance = new static();
        $instance->string = $string;

        return $instance;
    }

    public static function fromCodePoint(int ...$codes)
    {
        $string = '';
        foreach ($codes as $code) {
            if (0x80 > $code %= 0x200000) {
                $string .= chr($code);
            } elseif (0x800 > $code) {
                $string .= chr(0xC0 | $code >> 6).chr(0x80 | $code & 0x3F);
            } elseif (0x10000 > $code) {
                $string .= chr(0xE0 | $code >> 12).chr(0x80 | $code >> 6 & 0x3F).chr(0x80 | $code & 0x3F);
            } else {
                $string .= chr(0xF0 | $code >> 18).chr(0x80 | $code >> 12 & 0x3F).chr(0x80 | $code >> 6 & 0x3F).chr(0x80 | $code & 0x3F);
            }
        }

        $instance = new static();
        $instance->string = $string;

        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->string;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return '' === $this->string;
    }

    /**
     * {@inheritdoc}
     */
    public function explode(string $delimiter, int $limit = null): array
    {
        if ('' === $delimiter) {
            throw new InvalidArgumentException('Passing an empty delimiter is not supported by this method. Use getIterator() method instead.');
        }

        $chunks = array();
        foreach (preg_split('/'.preg_quote($delimiter).'/u', $this->string, $limit ?: -1) as $i => $string) {
            $chunks[$i] = $chunk = clone $this;
            $chunk->string = $string;
        }

        return $chunks;
    }

    /**
     * {@inheritdoc}
     */
    public function toLowerCase(): self
    {
        $result = clone $this;
        $result->string = mb_strtolower($this->string, 'UTF-8');

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function toUpperCase(): self
    {
        $result = clone $this;
        $result->string = mb_strtoupper($this->string, 'UTF-8');

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function toUpperCaseFirst($allWords = false): self
    {
        $callback = function ($matches) {
            return mb_convert_case($matches[1], MB_CASE_TITLE, 'UTF-8');
        };

        if ($allWords) {
            $string = preg_replace_callback('/\b(.)/u', $callback, $this->string);
        } else {
            $capitalLetter = mb_substr($this->string, 0, 1, 'UTF-8');
            $string = preg_replace_callback('/\b(.)/u', $callback, $capitalLetter).substr($this->string, strlen($capitalLetter));
        }

        $result = clone $this;
        $result->string = $string;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function toFoldedCase(bool $full = true): self
    {
        $string = str_replace(self::$commonCaseFold[0], self::$commonCaseFold[1], $this->string);

        if ($full) {
            static $fullCaseFold = false;
            $fullCaseFold or $fullCaseFold = require __DIR__.'/Resources/data/caseFolding_full.php';
            $string = str_replace($fullCaseFold[0], $fullCaseFold[1], $string);
        }

        $result = clone $this;
        $result->string = mb_strtolower($string, 'UTF-8');

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function trim(string $charsList = null): self
    {
        if (!preg_match('//u', $charsList)) {
            throw new InvalidArgumentException('Given chars list is not a valid UTF-8 encoded string.');
        }

        $charsList = $charsList ? preg_quote($charsList) : '[:space:]';

        $result = clone $this;
        $result->string = preg_replace("/^[$charsList]+|[$charsList]+\$/u", '', $this->string);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function trimLeft(string $charsList = null): self
    {
        if (!preg_match('//u', $charsList)) {
            throw new InvalidArgumentException('Given chars list is not a valid UTF-8 encoded string.');
        }

        $charsList = $charsList ? preg_quote($charsList) : '[:space:]';

        $result = clone $this;
        $result->string = preg_replace("/^[$charsList]+/u", '', $this->string);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function trimRight(string $charsList = null): self
    {
        if (!preg_match('//u', $charsList)) {
            throw new InvalidArgumentException('Given chars list is not a valid UTF-8 encoded string.');
        }

        $charsList = $charsList ? preg_quote($charsList) : '[:space:]';

        $result = clone $this;
        $result->string = preg_replace("/[$charsList]+\$/u", '', $this->string);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function append(string $suffix): self
    {
        if (!preg_match('//u', $suffix)) {
            throw new InvalidArgumentException('Given suffix is not a valid UTF-8 encoded string.');
        }

        $result = clone $this;
        $result->string .= $suffix;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(string $prefix): self
    {
        if (!preg_match('//u', $prefix)) {
            throw new InvalidArgumentException('Given prefix is not a valid UTF-8 encoded string.');
        }

        $result = clone $this;
        $result->string = $prefix.$this->string;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastSubstringOf(string $needle, bool $beforeNeedle = false)
    {
        if ('' === $needle) {
            return;
        }

        if (false === $part = mb_strrchr($this->string, $needle, $beforeNeedle, 'UTF-8')) {
            return;
        }

        $result = clone $this;
        $result->string = $part;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastSubstringOfIgnoreCase(string $needle, bool $beforeNeedle = false)
    {
        if ('' === $needle) {
            return;
        }

        if (false === $part = mb_strrichr($this->string, $needle, $beforeNeedle, 'UTF-8')) {
            return;
        }

        $result = clone $this;
        $result->string = $part;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function reverse(): self
    {
        $result = clone $this;
        $result->string = implode('', array_reverse(iterator_to_array($this)));

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function replace(string $from, string $to, int &$count = null): self
    {
        if (!preg_match('//u', $from)) {
            throw new InvalidArgumentException(sprintf('Given pattern "%s" is not a valid UTF-8 encoded string.', $from));
        }

        if (!preg_match('//u', $to)) {
            throw new InvalidArgumentException(sprintf('Given pattern replacement "%s" is not a valid UTF-8 encoded string.', $to));
        }

        $result = clone $this;
        $result->string = str_replace($from, $to, $this->string, $count);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function replaceAll(array $from, array $to, int &$count = null): self
    {
        if (count($from) !== count($to)) {
            throw new InvalidArgumentException('The number of search patterns does not match the number of pattern replacements.');
        }

        foreach ($from as $k => $pattern) {
            if (!is_string($pattern)) {
                throw new InvalidArgumentException(sprintf('Search pattern at key %s must be a valid string.', $k));
            }

            if (!preg_match('//u', $pattern)) {
                throw new InvalidArgumentException(sprintf('Given pattern "%s" is not a valid UTF-8 encoded string.', $pattern));
            }
        }

        foreach ($to as $k => $replacement) {
            if (!is_string($replacement)) {
                throw new InvalidArgumentException(sprintf('Pattern replacement at key %s must be a valid string.', $k));
            }

            if (!preg_match('//u', $replacement)) {
                throw new InvalidArgumentException(sprintf('Given pattern replacement "%s" is not a valid UTF-8 encoded string.', $replacement));
            }
        }

        $result = clone $this;
        $result->string = str_replace($from, $to, $this->string, $count);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function replaceIgnoreCase(string $from, string $to, int &$count = null): self
    {
        if (!preg_match('//u', $from)) {
            throw new InvalidArgumentException(sprintf('Given pattern "%s" is not a valid UTF-8 encoded string.', $from));
        }

        if (!preg_match('//u', $to)) {
            throw new InvalidArgumentException(sprintf('Given pattern replacement "%s" is not a valid UTF-8 encoded string.', $to));
        }

        $result = clone $this;
        $result->string = preg_replace(sprintf('/%s/ui', '' === $from ? '$^' : preg_quote($from, '/')), $to, $this->string, -1, $count);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function replaceAllIgnoreCase(array $from, array $to, int &$count = null): self
    {
        if (count($from) !== count($to)) {
            throw new InvalidArgumentException('The number of search patterns does not match the number of pattern replacements.');
        }

        foreach ($from as $k => $pattern) {
            if (!is_string($pattern)) {
                throw new InvalidArgumentException(sprintf('Search pattern at key %s must be a valid string.', $k));
            }

            if (!preg_match('//u', $pattern)) {
                throw new InvalidArgumentException(sprintf('Given pattern "%s" is not a valid UTF-8 encoded string.', $pattern));
            }

            $from[$k] = sprintf('/%s/ui', '' === $pattern ? '$^' : preg_quote($pattern, '/'));
        }

        foreach ($to as $k => $replacement) {
            if (!is_string($replacement)) {
                throw new InvalidArgumentException(sprintf('Pattern replacement at key %s must be a valid string.', $k));
            }

            if (!preg_match('//u', $replacement)) {
                throw new InvalidArgumentException(sprintf('Given pattern replacement "%s" is not a valid UTF-8 encoded string.', $replacement));
            }
        }

        $result = clone $this;
        $result->string = preg_replace($from, $to, $this->string, -1, $count);

        return $result;
    }
}
