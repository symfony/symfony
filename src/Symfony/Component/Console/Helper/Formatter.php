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

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * Provides helpers to format messages.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Formatter implements FormatterInterface
{
    public function formatSection($section, $message, $style = 'info'): string
    {
        return sprintf('<%s>[%s]</%s> %s', $style, $section, $style, $message);
    }

    public function formatBlock($messages, $style, $large = false): string
    {
        if (!\is_array($messages)) {
            $messages = [$messages];
        }

        $len = 0;
        $lines = [];
        foreach ($messages as $message) {
            $message = OutputFormatter::escape($message);
            $lines[] = sprintf($large ? '  %s  ' : ' %s ', $message);
            $len = max($this->strlen($message) + ($large ? 4 : 2), $len);
        }

        $messages = $large ? [str_repeat(' ', $len)] : [];
        for ($i = 0; isset($lines[$i]); ++$i) {
            $messages[] = $lines[$i].str_repeat(' ', $len - $this->strlen($lines[$i]));
        }
        if ($large) {
            $messages[] = str_repeat(' ', $len);
        }

        for ($i = 0; isset($messages[$i]); ++$i) {
            $messages[$i] = sprintf('<%s>%s</%s>', $style, $messages[$i], $style);
        }

        return implode("\n", $messages);
    }

    public function formatTime(int $secs): string
    {
        static $timeFormats = [
            [0, '< 1 sec'],
            [1, '1 sec'],
            [2, 'secs', 1],
            [60, '1 min'],
            [120, 'mins', 60],
            [3600, '1 hr'],
            [7200, 'hrs', 3600],
            [86400, '1 day'],
            [172800, 'days', 86400],
        ];

        foreach ($timeFormats as $index => $format) {
            if ($secs >= $format[0]) {
                if ((isset($timeFormats[$index + 1]) && $secs < $timeFormats[$index + 1][0])
                    || $index == \count($timeFormats) - 1
                ) {
                    if (2 == \count($format)) {
                        return $format[1];
                    }

                    return floor($secs / $format[2]).' '.$format[1];
                }
            }
        }
    }

    public function formatMemory(int $memory): string
    {
        if ($memory >= 1024 * 1024 * 1024) {
            return sprintf('%.1f GiB', $memory / 1024 / 1024 / 1024);
        }

        if ($memory >= 1024 * 1024) {
            return sprintf('%.1f MiB', $memory / 1024 / 1024);
        }

        if ($memory >= 1024) {
            return sprintf('%d KiB', $memory / 1024);
        }

        return sprintf('%d B', $memory);
    }

    public function strlen(string $string): int
    {
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return \strlen($string);
        }

        return mb_strwidth($string, $encoding);
    }

    public function substr(string $string, int $from, int $length = null): string
    {
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return substr($string, $from, $length);
        }

        return mb_substr($string, $from, $length, $encoding);
    }

    public function truncate(string $string, int $length, string $suffix = '...'): string
    {
        $computedLength = $length - $this->strlen($suffix);

        if ($computedLength > $this->strlen($string)) {
            return $string;
        }

        return $this->substr($string, 0, $length).$suffix;
    }

    public function strlenWithoutDecoration(OutputFormatterInterface $formatter, string $string): string
    {
        return $this->strlen($this->removeDecoration($formatter, $string));
    }

    public function removeDecoration(OutputFormatterInterface $formatter, string $string): string
    {
        $isDecorated = $formatter->isDecorated();
        $formatter->setDecorated(false);
        // remove <...> formatting
        $string = $formatter->format($string);
        // remove already formatted characters
        $string = preg_replace("/\033\[[^m]*m/", '', $string);
        $formatter->setDecorated($isDecorated);

        return $string;
    }
}
