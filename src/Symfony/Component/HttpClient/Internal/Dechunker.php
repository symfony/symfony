<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * A pure PHP alternative to the native "dechunk" stream filter.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Dechunker
{
    private const STATE_SIZE = 0;
    private const STATE_SIZE_EXT = 1;
    private const STATE_SIZE_LF = 2;
    private const STATE_DATA = 3;
    private const STATE_DATA_CR = 4;
    private const STATE_DATA_LF = 5;
    private const STATE_TRAILER = 6;

    private int $state = self::STATE_SIZE;
    private string $size = '';
    private int $remaining = 0;

    public function isFinished(): bool
    {
        return self::STATE_TRAILER === $this->state;
    }

    /**
     * @throws TransportException When the chunked encoding is malformed
     */
    public function dechunk(string $data): string
    {
        $out = '';
        $offset = 0;
        $len = \strlen($data);

        while ($offset < $len) {
            switch ($this->state) {
                case self::STATE_SIZE:
                    if (0 < $spn = strspn($data, '0123456789ABCDEFabcdef', $offset)) {
                        $this->size = ltrim($this->size.substr($data, $offset, $spn), '0') ?: '0';
                        $offset += $spn;

                        if (2 * \PHP_INT_SIZE - 1 < \strlen($this->size)) {
                            throw new TransportException('Malformed chunked body: chunk size is too big.');
                        }
                        break;
                    }

                    if ('' === $this->size) {
                        throw new TransportException('Malformed chunked body: invalid chunk size.');
                    }

                    $this->state = self::STATE_SIZE_EXT;
                    // no break
                case self::STATE_SIZE_EXT:
                    if ($len === $offset += strcspn($data, "\r\n", $offset)) {
                        break;
                    }

                    if ("\r" === $data[$offset]) {
                        ++$offset;
                    }

                    $this->state = self::STATE_SIZE_LF;
                    break;

                case self::STATE_SIZE_LF:
                    if ("\n" !== $data[$offset]) {
                        throw new TransportException('Malformed chunked body: invalid line ending after chunk size.');
                    }

                    ++$offset;
                    $this->state = ($this->remaining = (int) hexdec($this->size)) ? self::STATE_DATA : self::STATE_TRAILER;
                    $this->size = '';
                    break;

                case self::STATE_DATA:
                    $out .= $chunk = substr($data, $offset, $this->remaining);
                    $offset += \strlen($chunk);

                    if (0 === $this->remaining -= \strlen($chunk)) {
                        $this->state = self::STATE_DATA_CR;
                    }
                    break;

                case self::STATE_DATA_CR:
                    if ("\r" === $data[$offset]) {
                        ++$offset;
                    }

                    $this->state = self::STATE_DATA_LF;
                    break;

                case self::STATE_DATA_LF:
                    if ("\n" !== $data[$offset]) {
                        throw new TransportException('Malformed chunked body: invalid line ending after chunk data.');
                    }

                    ++$offset;
                    $this->state = self::STATE_SIZE;
                    break;

                case self::STATE_TRAILER:
                    // Trailer fields and anything else after the terminal chunk are ignored
                    return $out;
            }
        }

        return $out;
    }
}
