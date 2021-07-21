<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Block;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Terminal;

/**
 * @author Vadim Zharkov <hushker@gmail.com>
 */
class MessageBlock
{
    private const MAX_LINE_LENGTH = 120;

    public static function createBlock(
        array $messages,
        OutputFormatterInterface $formatter,
        string $type = null,
        string $style = null,
        string $prefix = ' ',
        bool $padding = false,
        bool $escape = false,
        int $lineLength = null,
        bool $isDecorated = false
    ): array {
        $lineLength = $lineLength ?: self::getTerminalLineLength();
        $indentLength = 0;
        $prefixLength = Helper::width(Helper::removeDecoration($formatter, $prefix));
        $lines = [];

        if (null !== $type) {
            $type = sprintf('[%s] ', $type);
            $indentLength = \strlen($type);
            $lineIndentation = str_repeat(' ', $indentLength);
        }

        // wrap and add newlines for each element
        foreach ($messages as $key => $message) {
            if ($escape) {
                $message = OutputFormatter::escape($message);
            }

            $decorationLength = Helper::width($message) - Helper::width(
                    Helper::removeDecoration($formatter, $message)
                );
            $messageLineLength = min(
                $lineLength - $prefixLength - $indentLength + $decorationLength,
                $lineLength
            );
            $messageLines = explode(\PHP_EOL, wordwrap($message, $messageLineLength, \PHP_EOL, true));
            foreach ($messageLines as $messageLine) {
                $lines[] = $messageLine;
            }

            if (\count($messages) > 1 && $key < \count($messages) - 1) {
                $lines[] = '';
            }
        }

        $firstLineIndex = 0;
        if ($padding && $isDecorated) {
            $firstLineIndex = 1;
            array_unshift($lines, '');
            $lines[] = '';
        }

        foreach ($lines as $i => &$line) {
            if (null !== $type) {
                $line = $firstLineIndex === $i ? $type.$line : $lineIndentation.$line;
            }

            $line = $prefix.$line;
            $line .= str_repeat(
                ' ',
                max(
                    $lineLength - Helper::width(
                        Helper::removeDecoration($formatter, $line)
                    ),
                    0
                )
            );

            if ($style) {
                $line = sprintf('<%s>%s</>', $style, $line);
            }
        }

        return $lines;
    }

    private static function getTerminalLineLength(): int
    {
        $width = (new Terminal())->getWidth() ?: self::MAX_LINE_LENGTH;

        return $width - (int) (\DIRECTORY_SEPARATOR === '\\');
    }
}
