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
        $output = $current = '';
        $openTagRegex = '[a-z](?:[^\\\\<>]*+ | \\\\.)*';
        $closeTagRegex = '[a-z][^<>]*+';
        $currentLineLength = 0;
        preg_match_all("#<(($openTagRegex) | /($closeTagRegex)?)>#ix", $message, $matches, \PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $i => $match) {
            $pos = $match[1];
            $text = $match[0];

            if (0 != $pos && '\\' == $message[$pos - 1]) {
                continue;
            }

            // tags are delimited by "<" and ">", which are ASCII, so a tag offset can never
            // fall inside a multibyte sequence: slicing by bytes is safe here
            $output .= $this->applyCurrentStyle(substr($message, $offset, $pos - $offset), $current, $width, $currentLineLength);
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
                $output .= $this->applyCurrentStyle($text, $current, $width, $currentLineLength);
            } elseif ($open) {
                $this->styleStack->push($style);
            } else {
                $this->styleStack->pop($style);
            }
        }

        $output .= $this->applyCurrentStyle(substr($message, $offset), $current, $width, $currentLineLength);

        return strtr($output, ["\0" => '\\', '\\<' => '<', '\\>' => '>']);
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
        if (isset($this->styles[$string])) {
            return $this->styles[$string];
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
    private function applyCurrentStyle(string $text, string &$current, int $width, int &$currentLineLength): string
    {
        if ('' === $text) {
            return '';
        }

        if (!$width || str_contains($text, "\e") && '' === preg_replace(self::ANSI_REGEX, '', $text)) {
            return $this->isDecorated() ? $this->styleStack->getCurrent()->apply($text) : $text;
        }

        if (!$currentLineLength && '' !== $current) {
            if (str_contains($text, "\e")) {
                $text = preg_replace_callback('/^(?:[\\x00\\x09-\\x0B\\x0D\\x20]|'.self::ANSI_PATTERN.')++/', static function ($match) {
                    preg_match_all(self::ANSI_REGEX, $match[0], $ansi);

                    return implode('', $ansi[0]);
                }, $text);
            } else {
                $text = ltrim($text);
            }
        }

        if ($currentLineLength) {
            $lines = explode("\n", $text, 2);
            $prefix = $this->substr($lines[0], 0, $i = $width - $currentLineLength)."\n";
            $text = $this->substr($lines[0], $i);

            if (isset($lines[1])) {
                // $prefix may contain the full first line in which the \n is already a part of $prefix.
                if ('' !== $text) {
                    $text .= "\n";
                }

                $text .= $lines[1];
            }
        } else {
            $prefix = '';
        }

        preg_match('/(\\n)(?:'.self::ANSI_PATTERN.')*+$/', $text, $matches);
        $text = $prefix.$this->addLineBreaks($text, $width);
        if (str_contains($text, "\e")) {
            $parts = preg_split('/('.self::ANSI_PATTERN.')/', $text, -1, \PREG_SPLIT_DELIM_CAPTURE);
            $sequences = '';
            for ($i = \count($parts) - 1; $i >= 0; --$i) {
                if ($i % 2) {
                    $sequences = $parts[$i].$sequences;
                } elseif ('' !== $parts[$i] = rtrim($parts[$i], "\n")) {
                    break;
                }
            }
            $text = implode('', \array_slice($parts, 0, $i + 1)).$sequences;
        } else {
            $text = rtrim($text, "\n");
        }
        $text .= $matches[1] ?? '';

        if (!$currentLineLength && '' !== $current && !str_ends_with($current, "\n")) {
            $text = "\n".$text;
        }

        $lines = explode("\n", $text);

        foreach ($lines as $i => $line) {
            $lineLength = Helper::length(str_contains($line, "\e") ? preg_replace(self::ANSI_REGEX, '', $line) : $line);
            $currentLineLength = 0 === $i ? $currentLineLength + $lineLength : $lineLength;
            if ($width <= $currentLineLength) {
                $currentLineLength = 0;
            }
        }

        $currentLines = [];
        foreach ($lines as $i => $line) {
            $currentLine = str_contains($line, "\e") ? preg_replace(self::ANSI_REGEX, '', $line) : $line;
            if ($this->isDecorated()) {
                $lines[$i] = $this->styleStack->getCurrent()->apply($line);
                $currentLine = $currentLine === $line ? $lines[$i] : $this->styleStack->getCurrent()->apply($currentLine);
            }
            $currentLines[] = $currentLine;
        }
        $current .= implode("\n", $currentLines);

        return implode("\n", $lines);
    }

    private function addLineBreaks(string $text, int $width): string
    {
        $encoding = mb_detect_encoding($text, null, true) ?: 'UTF-8';

        if (!str_contains($text, "\e")) {
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
            $mask .= "\n" === $token ? '#' : (' ' === $token ? ' ' : '?');
        }

        if (!$chars) {
            return $pending;
        }

        $wrapped = rtrim($mask, '#');
        $mask = wordwrap($wrapped, $width, '#', true).substr($mask, \strlen($wrapped));
        $result = '';
        $j = 0;
        foreach (str_split($mask) as $char) {
            if ('#' === $char) {
                if (isset($chars[$j]) && ("\n" === $chars[$j][1] || ' ' === $chars[$j][1])) {
                    $result .= $chars[$j++][0];
                }
                $result .= "\n";
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
}
