<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Util;

use Symfony\Component\Yaml\Exception\ParseException;

/**
 * @author Guilhem N. <egetick@gmail.com>
 * @author Nicolas "Exter-N" L. <exter-n@exter-n.fr>
 *
 * @internal
 */
class StringReader
{
    /**
     * @var string
     */
    const WHITE_SPACE_MASK = "\t ";

    private $data;
    private $start = 0;
    private $end;
    private $offset = 0;

    /**
     * @param string $data
     */
    public function __construct($data)
    {
        $this->data = $data;
        $this->end = strlen($data);
    }

    /**
     * @param string $char
     *
     * @return bool
     */
    public function readChar($char)
    {
        if (isset($this->data[$this->offset]) && $char === $this->data[$this->offset]) {
            ++$this->offset;

            return true;
        }

        return false;
    }

    public function expectChar($char)
    {
        if (!$this->readChar($char)) {
            throw new ParseException(sprintf('Expected "%s", got "%s".', $char, $this->peek()));
        }
    }

    /**
     * @param string[]|\Traversable $strings
     * @param bool                  $caseInsensitive
     */
    public function readAny($strings, $caseInsensitive = false)
    {
        foreach ($strings as $string) {
            if ($this->readString($string, $caseInsensitive)) {
                return $string;
            }
        }
    }

    /**
     * @param string $string
     * @param bool   $caseInsensitive
     *
     * @return bool
     */
    public function readString($string, $caseInsensitive = false)
    {
        $length = strlen($string);
        if (!isset($this->data[$this->offset + $length - 1])) {
            return false;
        }

        if (0 !== substr_compare($this->data, $string, $this->offset, $length, $caseInsensitive)) {
            return false;
        }

        $this->offset += $length;

        return true;
    }

    /**
     * @param string $mask
     *
     * @return string
     */
    public function readSpan($mask)
    {
        return $this->internalRead(strspn($this->data, $mask, $this->offset));
    }

    /**
     * @param string $mask
     *
     * @return string
     */
    public function readCSpan($mask)
    {
        return $this->internalRead(strcspn($this->data, $mask, $this->offset));
    }

    /**
     * @return int
     */
    public function consumeWhiteSpace()
    {
        $length = strspn($this->data, self::WHITE_SPACE_MASK, $this->offset);
        $this->offset += $length;

        return $length;
    }

    /**
     * @return int
     */
    public function getRemainingByteCount()
    {
        return $this->end - $this->offset;
    }

    public function isFullyConsumed()
    {
        return 0 === $this->getRemainingByteCount();
    }

    /**
     * Returns the next byte.
     *
     * @return string|null null if not enough data
     */
    public function peek()
    {
        if (isset($this->data[$this->offset])) {
            return $this->data[$this->offset];
        }
    }

    /**
     * @param int $byteCount Number of bytes to read
     *
     * @return string
     */
    public function read($byteCount)
    {
        $maxByteCount = $this->getRemainingByteCount();
        $byteCount = min($byteCount, $maxByteCount);

        return $this->internalRead($byteCount);
    }

    /**
     * @return string
     */
    public function readToEnd()
    {
        return $this->internalRead($this->getRemainingByteCount());
    }

    /**
     * No checks are performed, used internally when the source is sure.
     */
    private function internalRead($byteCount)
    {
        if (0 === $byteCount) {
            return '';
        }

        $substr = substr($this->data, $this->offset, $byteCount);
        $this->offset += $byteCount;

        return $substr;
    }

    public function __toString()
    {
        return $this->data;
    }
}
