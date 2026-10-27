<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Formatter;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\Helper;

use function Symfony\Component\String\b;

/**
 * Formatter class for console output.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class OutputFormatter implements WrappableOutputFormatterInterface
{
    private const ANSI_PATTERN = '\\x1B(?:\\[[0-9;:]*m|\\]8;[^;]*;[^\\x1B]*\\x1B\\\\)';
    private const ANSI_REGEX = '/'.self::ANSI_PATTERN.'/';

    private bool $decorated;
    private array $styles = [];
    private OutputFormatterStyleStack $styleStack;
    private array $charWidths = [];

    public function __clone()
    {
        $this->styleStack = clone $this->styleStack;
        foreach ($this->styles as $key => $value) {
            $this->styles[$key] = clone $value;
        }
    }

    /**
     * Escapes "<" and ">" special chars in given text.
     */
    public static function escape(string $text): string
    {
        $text = str_replace(['<', '>'], ['\\<', '\\>'], $text);

        return self::escapeTrailingBackslash($text);
    }

    /**
     * Escapes trailing "\" in given text.
     *
     * @internal
     */
    public static function escapeTrailingBackslash(string $text): string
    {
        if (str_ends_with($text, '\\')) {
            $len = \strlen($text);
            $text = rtrim($text, '\\');
            $text = str_replace("\0", '', $text);
            $text .= str_repeat("\0", $len - \strlen($text));
        }

        return $text;
    }

    /**
     * Initializes console output formatter.
     *
     * @param OutputFormatterStyleInterface[] $styles Array of "name => FormatterStyle" instances
     */
    public function __construct(bool $decorated = false, array $styles = [])
    {
        $this->decorated = $decorated;

        $this->setStyle('error', new OutputFormatterStyle('white', 'red'));
        $this->setStyle('info', new OutputFormatterStyle('green'));
        $this->setStyle('comment', new OutputFormatterStyle('yellow'));
        $this->setStyle('question', new OutputFormatterStyle('black', 'cyan'));

        foreach ($styles as $name => $style) {
            $this->setStyle($name, $style);
        }

        $this->styleStack = new OutputFormatterStyleStack();
    }

    /**
     * @return void
     */
    public function setDecorated(bool $decorated)
    {
        $this->decorated = $decorated;
    }

    public function isDecorated(): bool
    {
        return $this->decorated;
    }

    /**
     * @return void
     */
    public function setStyle(string $name, OutputFormatterStyleInterface $style)
    {
        $this->styles[strtolower($name)] = $style;
    }

    public function hasStyle(string $name): bool
    {
        return isset($this->styles[strtolower($name)]);
    }

    public function getStyle(string $name): OutputFormatterStyleInterface
    {
        if (!$this->hasStyle($name)) {
            throw new InvalidArgumentException(\sprintf('Undefined style: "%s".', $name));
        }

        return $this->styles[strtolower($name)];
    }

    public function format(?string $message): ?string
    {
        return $this->formatAndWrap($message, 0);
    }

    /**
     * @return string
     */
    public function formatAndWrap(?string $message, int $width)
    {
        if (null === $message) {
            return '';
        }

        $offset = 0;
        $output = '';
        $openTagRegex = '[a-z](?:[^\\\\<>]*+ | \\\\.)*';
        $closeTagRegex = '[a-z][^<>]*+';
        $currentLineLength = 0;
        $lineBreak = false;
        preg_match_all("#<(($openTagRegex) | /($closeTagRegex)?)>#ix", $message, $matches, \PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $i => $match) {
            $pos = $match[1];
            $text = $match[0];

            if (0 != $pos && '\\' == $message[$pos - 1]) {
                continue;
            }

            // tags are delimited by "<" and ">", which are ASCII, so a tag offset can never
            // fall inside a multibyte sequence: slicing by bytes is safe here
            $output .= $this->applyCurrentStyle(substr($message, $offset, $pos - $offset), $width, $currentLineLength, $lineBreak);
            $offset = $pos + \strlen($text);

            // opening tag?
            if ($open = '/' !== $text[1]) {
                $tag = $matches[1][$i][0];
            } else {
                $tag = $matches[3][$i][0] ?? '';
            }

            if (!$open && !$tag) {
                // </>
                $this->styleStack->pop();
            } elseif (null === $style = $this->createStyleFromString($tag)) {
                $output .= $this->applyCurrentStyle($text, $width, $currentLineLength, $lineBreak);
            } elseif ($open) {
                $this->styleStack->push($style);
            } else {
                $this->styleStack->pop($style);
            }
        }

        $output .= $this->applyCurrentStyle(substr($message, $offset), $width, $currentLineLength, $lineBreak);

        if ($lineBreak) {
            $output .= "\n";
        }

        if ($width && str_contains($output, "\r")) {
            // keep "\r\n" line breaks together when ANSI codes end the line
            $output = preg_replace('/\r((?:'.self::ANSI_PATTERN.')++)(?=\n|$)/', "\$1\r", $output);
        }

        return str_replace("\0", '\\', $output);
    }

    public function getStyleStack(): OutputFormatterStyleStack
    {
        return $this->styleStack;
    }

    /**
     * Tries to create new style instance from string.
     */
    private function createStyleFromString(string $string): ?OutputFormatterStyleInterface
    {
        if (isset($this->styles[$lowerString = strtolower($string)])) {
            return $this->styles[$lowerString];
        }

        if (!preg_match_all('/([^=]+)=([^;]+)(;|$)/', $string, $matches, \PREG_SET_ORDER)) {
            return null;
        }

        $style = new OutputFormatterStyle();
        foreach ($matches as $match) {
            array_shift($match);
            $match[0] = strtolower($match[0]);

            if ('fg' == $match[0]) {
                $style->setForeground(strtolower($match[1]));
            } elseif ('bg' == $match[0]) {
                $style->setBackground(strtolower($match[1]));
            } elseif ('href' === $match[0]) {
                $url = preg_replace('{\\\\([<>])}', '$1', $match[1]);
                $style->setHref($url);
            } elseif ('options' === $match[0]) {
                preg_match_all('([^,;]+)', strtolower($match[1]), $options);
                $options = array_shift($options);
                foreach ($options as $option) {
                    $style->setOption($option);
                }
            } else {
                return null;
            }
        }

        return $style;
    }

    /**
     * Applies current style from stack to text, if must be applied.
     */
    private function applyCurrentStyle(string $text, int $width, int &$currentLineLength, bool &$lineBreak): string
    {
        if ('' === $text) {
            return '';
        }

        if (!$width || str_contains($text, "\e") && '' === preg_replace(self::ANSI_REGEX, '', $text)) {
            $text = str_replace(['\\<', '\\>'], ['<', '>'], $text);

            return $this->isDecorated() ? $this->styleStack->getCurrent()->apply($text) : $text;
        }

        $text = str_replace(['\\<', '\\>'], ['<', '>'], $text);
        $prefix = '';
        $visibleText = str_contains($text, "\e") ? preg_replace(self::ANSI_REGEX, '', $text) : $text;

        if ($currentLineLength >= $width) {
            // the current line is full: drop the whitespace that follows and break the line before the next character
            if (str_contains($text, "\e")) {
                $text = preg_replace_callback('/^(?:[\\x00\\x09\\x0B\\x0D\\x20]|'.self::ANSI_PATTERN.')++/', static function ($match) {
                    preg_match_all(self::ANSI_REGEX, $match[0], $ansi);

                    return implode('', $ansi[0]);
                }, $text);
                $visibleText = ltrim($visibleText, " \t\r\0\x0B");
            } else {
                $text = $visibleText = ltrim($text, " \t\r\0\x0B");
            }

            // whitespace at the end of the message still breaks the line
            if ($lineBreak = '' === $visibleText) {
                return '' === $text || !$this->isDecorated() ? $text : $this->styleStack->getCurrent()->apply($text);
            }

            if ("\n" !== $visibleText[0]) {
                $prefix = "\n";
            }

            $currentLineLength = 0;
        }

        $trailingNewlines = substr($visibleText, \strlen(rtrim($visibleText, "\r\n")));

        if ($currentLineLength) {
            $lines = explode("\n", $text, 2);
            $eol = "\n";
            if (isset($lines[1]) && str_ends_with($lines[0], "\r")) {
                $lines[0] = substr($lines[0], 0, -1);
                $eol = "\r\n";
            }
            $i = $width - $currentLineLength;
            if ($widths = $this->getWidths($lines[0])) {
                $room = $i;
                $i = \count($widths);
                foreach ($widths as $j => $charWidth) {
                    if (0 > $room -= $charWidth) {
                        $i = $j;
                        break;
                    }
                }
            }
            $prefix = ($i ? $this->substr($lines[0], 0, $i) : '')."\n";
            $text = $i ? $this->substr($lines[0], $i) : $lines[0];

            if (isset($lines[1])) {
                // $prefix may contain the full first line in which the \n is already a part of $prefix.
                if ('' !== $text) {
                    $text .= $eol;
                } else {
                    $prefix = substr($prefix, 0, -1).$eol;
                }

                $text .= $lines[1];
            }
        }

        $text = $prefix.$this->addLineBreaks($text, $width);
        if (str_contains($text, "\e")) {
            $parts = preg_split('/('.self::ANSI_PATTERN.')/', $text, -1, \PREG_SPLIT_DELIM_CAPTURE);
            $sequences = '';
            for ($i = \count($parts) - 1; $i >= 0; --$i) {
                if ($i % 2) {
                    $sequences = $parts[$i].$sequences;
                } elseif ('' !== $parts[$i] = rtrim($parts[$i], "\r\n")) {
                    break;
                }
            }
            $text = implode('', \array_slice($parts, 0, $i + 1)).$sequences;
        } else {
            $text = rtrim($text, "\r\n");
        }
        $text .= $trailingNewlines;

        $lines = explode("\n", $text);

        foreach ($lines as $i => $line) {
            $lineLength = array_sum($this->getWidths($line)) ?: Helper::length(str_contains($line, "\e") ? preg_replace(self::ANSI_REGEX, '', $line) : $line);
            $currentLineLength = 0 === $i ? $currentLineLength + $lineLength : $lineLength;
        }

        foreach ($lines as $i => $line) {
            $lines[$i] = $this->isDecorated() ? $this->styleStack->getCurrent()->apply($line) : $line;
        }

        return implode("\n", $lines);
    }

    private function addLineBreaks(string $text, int $width): string
    {
        $encoding = mb_detect_encoding($text, null, true) ?: 'UTF-8';
        $widths = $this->getWidths($text);

        if (!$widths && !str_contains($text, "\e") && !str_contains($text, "\r\n")) {
            return b($text)->toUnicodeString($encoding)->wordwrap($width, "\n", true)->toByteString($encoding);
        }

        $chars = [];
        $mask = $pending = '';
        foreach ($this->getTokens($text) as $token) {
            if (preg_match(self::ANSI_REGEX, $token)) {
                $pending .= $token;
                continue;
            }
            $chars[] = [$pending, $token];
            $pending = '';
            $mask .= "\n" === $token || "\r\n" === $token ? '#' : (' ' === $token ? ' ' : '?');
        }

        if (!$chars) {
            return $pending;
        }

        $wrapped = rtrim($mask, '#');
        $mask = ($widths ? $this->wordwrap($wrapped, $width, $widths) : wordwrap($wrapped, $width, '#', true)).substr($mask, \strlen($wrapped));
        $result = '';
        $j = 0;
        foreach (str_split($mask) as $char) {
            if ('#' === $char) {
                $eol = "\n";
                if (isset($chars[$j]) && ("\n" === $chars[$j][1] || "\r\n" === $chars[$j][1] || ' ' === $chars[$j][1])) {
                    $eol = ' ' === $chars[$j][1] ? "\n" : $chars[$j][1];
                    $result .= $chars[$j++][0];
                }
                $result .= $eol;
            } else {
                $result .= implode('', $chars[$j++]);
            }
        }

        return $result.$pending;
    }

    private function substr(string $text, int $from, ?int $length = null): string
    {
        if (!str_contains($text, "\e")) {
            return Helper::substr($text, $from, $length);
        }

        $result = '';
        $position = 0;
        foreach ($this->getTokens($text) as $token) {
            $isAnsi = preg_match(self::ANSI_REGEX, $token);
            if ($position >= $from && (null === $length || $position < $from + $length || $isAnsi && $position === $from + $length)
                && (!$isAnsi || !$from || $position > $from)
            ) {
                $result .= $token;
            }
            if (!$isAnsi) {
                ++$position;
            }
        }

        return $result;
    }

    private function getTokens(string $text): iterable
    {
        $encoding = mb_detect_encoding($text, null, true) ?: 'UTF-8';
        foreach (preg_split('/('.self::ANSI_PATTERN.')/', $text, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $part) {
            if (preg_match(self::ANSI_REGEX, $part)) {
                yield $part;
                continue;
            }
            foreach (b($part)->toUnicodeString($encoding)->chunk() as $char) {
                yield (string) $char->toByteString($encoding);
            }
        }
    }

    /**
     * Returns the number of columns of each visible character, or an empty array when none uses more than one column.
     *
     * @return list<int>
     */
    private function getWidths(string $text): array
    {
        if (!preg_match('/[^\x00-\x{10FF}]/u', $text)) {
            return [];
        }

        $widths = [];
        $wide = false;
        foreach ($this->getTokens($text) as $token) {
            if (!preg_match(self::ANSI_REGEX, $token)) {
                $widths[] = $charWidth = 2 < \strlen($token) ? ($this->charWidths[$token] ??= Helper::width($token)) : 1;
                $wide = $wide || 1 < $charWidth;
            }
        }

        return $wide ? $widths : [];
    }

    /**
     * Wraps a mask like wordwrap() does when cutting long words, but using the number of columns of each character.
     *
     * @param list<int> $widths
     */
    private function wordwrap(string $mask, int $width, array $widths): string
    {
        $result = '';
        $start = $lastSpace = $lineWidth = 0;

        for ($i = 0, $length = \strlen($mask); $i < $length; ++$i) {
            if ('#' === $mask[$i]) {
                $result .= substr($mask, $start, $i + 1 - $start);
                $start = $lastSpace = $i + 1;
                $lineWidth = 0;

                continue;
            }

            if (' ' === $mask[$i]) {
                if ($lineWidth >= $width) {
                    $result .= substr($mask, $start, $i - $start).'#';
                    $start = $i + 1;
                    $lineWidth = 0;
                } else {
                    ++$lineWidth;
                }
                $lastSpace = $i;

                continue;
            }

            if ($lineWidth + $widths[$i] > $width && $start < $lastSpace) {
                $result .= substr($mask, $start, $lastSpace - $start).'#';
                $start = $lastSpace = $lastSpace + 1;
                $lineWidth = array_sum(\array_slice($widths, $start, $i - $start));
            }

            // cut long words, and also before a wide character that still doesn't fit after breaking at the last space
            if ($lineWidth && $lineWidth + $widths[$i] > $width) {
                $result .= substr($mask, $start, $i - $start).'#';
                $start = $lastSpace = $i;
                $lineWidth = 0;
            }

            $lineWidth += $widths[$i];
        }

        return $result.substr($mask, $start);
    }
}
