<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compiler\Parser;

/**
 * Parses JavaScript content to identify sequences of strings, comments, etc.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
class JavascriptParser
{
    private const STATE_DEFAULT = 'DEFAULT';
    private const STATE_COMMENT = 'COMMENT';
    private const STATE_STRING = 'STRING';

    private int $cursor = 0;

    private int $contentEnd;

    private string $pattern;

    private ?CodeSequence $currentSequence = null;

    /**
     * @var CodeSequence[]
     */
    private array $sequences = [];

    private function __construct(
        private string $content,
    ) {
        $this->contentEnd = \strlen($content);

        $chars = [
            '/*',   // Multi-line comment
            '//',   // Single-line comment
            '"',    // Double quote
            '\'',   // Single quote
            '`',    // Backtick
        ];
        $this->pattern = '/'.implode('|', array_map(fn ($ch) => preg_quote($ch, '/'), $chars)).'/';
    }

    public static function create(string $content): self
    {
        return new self($content);
    }

    /**
     * @return CodeSequence[]
     */
    public function getSequences(): array
    {
        return $this->sequences;
    }

    public function getCurrentSequence(): ?CodeSequence
    {
        return $this->currentSequence;
    }

    public function isExecutable(): bool
    {
        return self::STATE_DEFAULT === $this->currentSequence?->getType();
    }

    public function parseUntil(int $position): void
    {
        if ($position > $this->contentEnd) {
            throw new \InvalidArgumentException('Cannot parse beyond the end of the content.');
        }
        if ($position < $this->cursor) {
            throw new \InvalidArgumentException('Cannot parse backwards.');
        }

        while ($this->cursor <= $position) {
            // Current CodeSequence ?
            if (null !== $this->currentSequence) {
                if ($this->currentSequence->getEnd() > $position) {
                    $this->cursor = $position;

                    return;
                }

                $this->cursor = $this->currentSequence->getEnd();
                $this->currentSequence = null;
            }

            preg_match($this->pattern, $this->content, $matches, \PREG_OFFSET_CAPTURE, $this->cursor);
            if (!$matches) {
                $this->cursor = $position;
                $this->pushSequence(self::STATE_DEFAULT, $this->cursor, $this->contentEnd);

                return;
            }

            $matchPos = (int) $matches[0][1];
            $matchChar = $matches[0][0];

            if ($matchPos > $position) {
                $this->pushSequence(self::STATE_DEFAULT, $this->cursor, $matchPos - 1);
                $this->cursor = $position;

                return;
            }

            // Multi-line comment
            if ('/*' === $matchChar) {
                if (false === $endPos = strpos($this->content, '*/', $matchPos + 2)) {
                    $this->cursor = $position;
                    $this->pushSequence(self::STATE_COMMENT, $matchPos, $this->contentEnd);

                    return;
                }

                $this->cursor = min($endPos + 2, $position);
                $this->pushSequence(self::STATE_COMMENT, $matchPos, $endPos + 2);
                continue;
            }

            // Single-line comment
            if ('//' === $matchChar) {
                if (false === $endPos = strpos($this->content, "\n", $matchPos + 2)) {
                    $this->cursor = $position;
                    $this->pushSequence(self::STATE_COMMENT, $matchPos, $this->contentEnd);

                    return;
                }

                $this->cursor = min($endPos + 1, $position);
                $this->pushSequence(self::STATE_COMMENT, $matchPos, $endPos + 1);
                continue;
            }

            // Single-line string
            if ('"' === $matchChar || "'" === $matchChar) {
                if (false === $endPos = strpos($this->content, $matchChar, $matchPos + 1)) {
                    $this->cursor = $position;
                    $this->pushSequence(self::STATE_STRING, $matchPos, $this->contentEnd);

                    return;
                }
                while (false !== $endPos && '\\' == $this->content[$endPos - 1]) {
                    $endPos = strpos($this->content, $matchChar, $endPos + 1);
                }

                $this->cursor = min($endPos + 1, $position);
                $this->pushSequence(self::STATE_STRING, $matchPos, $endPos + 1);
                continue;
            }

            // Multi-line string
            if ('`' === $matchChar) {
                if (false === $endPos = strpos($this->content, $matchChar, $matchPos + 1)) {
                    $this->cursor = $position;
                    $this->pushSequence(self::STATE_STRING, $matchPos, $this->contentEnd);

                    return;
                }
                while (false !== $endPos && '\\' == $this->content[$endPos - 1]) {
                    $endPos = strpos($this->content, $matchChar, $endPos + 1);
                }

                $this->cursor = min($endPos + 1, $position);
                $this->pushSequence(self::STATE_STRING, $matchPos, $endPos + 1);
                continue;
            }
        }
    }

    private function pushSequence(string $type, int $start, int $end): void
    {
        $this->sequences[] = $this->currentSequence = new CodeSequence($type, $start, $end);
    }
}
