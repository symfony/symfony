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

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\Helper;

/**
 * Generates formatted block lines.
 *
 * @author Vadim Zharkov <hushker@gmail.com>
 */
class MessageBlock
{
    /**
     * @var OutputFormatterInterface
     */
    private $formatter;

    /**
     * @var int
     */
    private $lineLength;

    /**
     * @var iterable
     */
    private $messages;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $style;

    /**
     * @var string
     */
    private $prefix = ' ';

    /**
     * @var bool
     */
    private $padding = false;

    /**
     * @var bool
     */
    private $escape = false;

    /**
     * @return MessageBlock
     */
    public function setFormatter(OutputFormatterInterface $formatter): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * @return MessageBlock
     */
    public function setLineLength(int $lineLength): self
    {
        $this->lineLength = $lineLength;

        return $this;
    }

    /**
     * @return MessageBlock
     */
    public function setMessages(iterable $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * @return MessageBlock
     */
    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param string $style
     *
     * @return MessageBlock
     */
    public function setStyle(?string $style): self
    {
        $this->style = $style;

        return $this;
    }

    /**
     * @param string $prefix
     *
     * @return MessageBlock
     */
    public function setPrefix(?string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @return MessageBlock
     */
    public function setPadding(bool $padding): self
    {
        $this->padding = $padding;

        return $this;
    }

    /**
     * @return MessageBlock
     */
    public function setEscape(bool $escape): self
    {
        $this->escape = $escape;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getLines(): array
    {
        $indentLength = 0;
        $prefixLength = Helper::strlenWithoutDecoration($this->formatter, $this->prefix);
        $lines = [];

        $lineIndentation = '';
        if (null !== $this->type) {
            $this->type = sprintf('[%s] ', $this->type);
            $indentLength = \strlen($this->type);
            $lineIndentation = str_repeat(' ', $indentLength);
        }

        // wrap and add newlines for each element
        foreach ($this->messages as $key => $message) {
            if ($this->escape) {
                $message = OutputFormatter::escape($message);
            }

            $lines = array_merge($lines, explode(\PHP_EOL,
                wordwrap($message, $this->lineLength - $prefixLength - $indentLength, \PHP_EOL, true)));

            if (\count($this->messages) > 1 && $key < \count($this->messages) - 1) {
                $lines[] = '';
            }
        }

        $firstLineIndex = 0;
        if ($this->padding) {
            $firstLineIndex = 1;
            array_unshift($lines, '');
            $lines[] = '';
        }

        foreach ($lines as $i => &$line) {
            if (null !== $this->type) {
                $line = $firstLineIndex === $i ? $this->type.$line : $lineIndentation.$line;
            }

            $line = $this->prefix.$line;
            $line .= str_repeat(' ', $this->lineLength - Helper::strlenWithoutDecoration($this->formatter, $line));

            if ($this->style) {
                $line = sprintf('<%s>%s</>', $this->style, $line);
            }
        }

        return $lines;
    }
}
