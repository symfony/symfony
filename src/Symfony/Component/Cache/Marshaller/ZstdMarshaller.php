<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Marshaller;

use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * Compresses values using zstd_compress().
 *
 * The zstd magic number tells frames from raw values, so a value stays raw when
 * compression does not pay. A frame stating more than $maxLength bytes, or a
 * value that is not exactly one frame, is refused and read as a miss.
 *
 * @author Marcin Gil <mg@artigo.pl>
 */
final class ZstdMarshaller implements MarshallerInterface
{
    /**
     * The four bytes every zstd frame starts with.
     */
    private const MAGIC = "\x28\xB5\x2F\xFD";

    /**
     * The most a block holds, compressed or not (RFC 8878, 3.1.1.2).
     */
    private const BLOCK_SIZE_MAX = 128 * 1024;

    /**
     * @param int $compressionLevel 1 to 22, 3 being zstd's default
     * @param int $threshold        Values shorter than this many bytes are stored raw
     * @param int $maxLength        Values longer than this many bytes are stored raw, and frames stating more are refused
     */
    public function __construct(
        private MarshallerInterface $marshaller,
        private int $compressionLevel = 3,
        private int $threshold = 256,
        private int $maxLength = 16 * 1024 * 1024,
    ) {
        if (!\function_exists('zstd_compress')) {
            throw new CacheException('The "zstd" PHP extension is not loaded.');
        }

        if (1 > $compressionLevel || 22 < $compressionLevel) {
            throw new InvalidArgumentException(\sprintf('The compression level must be between 1 and 22, "%d" given.', $compressionLevel));
        }

        if (0 > $threshold) {
            throw new InvalidArgumentException(\sprintf('The threshold must not be negative, "%d" given.', $threshold));
        }

        if (1 > $maxLength) {
            throw new InvalidArgumentException(\sprintf('The maximum length must be positive, "%d" given.', $maxLength));
        }
    }

    public function marshall(array $values, ?array &$failed): array
    {
        $values = $this->marshaller->marshall($values, $failed);

        foreach ($values as $id => $value) {
            $escape = str_starts_with($value, self::MAGIC);
            $length = \strlen($value);

            if (!$escape && $length < $this->threshold) {
                continue;
            }

            // a frame stating more than $maxLength would be refused on read
            $frame = $length <= $this->maxLength ? zstd_compress($value, $this->compressionLevel) : false;

            if (\is_string($frame) && ($escape || \strlen($frame) < $length)) {
                $values[$id] = $frame;
            } elseif ($escape) {
                // stored raw, it would be read as a frame
                unset($values[$id]);
                $failed[] = $id;
            }
        }

        return $values;
    }

    public function unmarshall(string $value): mixed
    {
        if (str_starts_with($value, self::MAGIC)) {
            // RFC 8878, 3.1.1.1.1: a decoder refuses a frame with the reserved bit set
            if (0 !== (\ord($value[4] ?? "\0") & 0x08)) {
                throw new \DomainException('Refused a zstd frame whose header sets the reserved bit.');
            }

            [$headerLength, $size] = self::readHeader($value);

            // checked before inflating: zstd_uncompress() allocates the stated size first
            if (null === $size || $size < 1 || $size > $this->maxLength) {
                throw new \DomainException('Refused a zstd frame that states no content size or one out of bounds.');
            }

            // zstd_uncompress() stops at the end of the first frame and ignores whatever follows it
            if (self::frameLength($value, $headerLength) !== \strlen($value)) {
                throw new \DomainException('Refused a zstd value that is not exactly one whole frame.');
            }

            // a corrupt frame answers false, with a warning
            $inflated = @zstd_uncompress($value);

            if (!\is_string($inflated) || \strlen($inflated) !== $size) {
                throw new \DomainException('Failed to decompress a zstd frame.');
            }

            $value = $inflated;
        }

        return $this->marshaller->unmarshall($value);
    }

    /**
     * @return array{int, ?int} The header length and the content size it states, if any (RFC 8878, 3.1.1.1)
     */
    private static function readHeader(string $value): array
    {
        $descriptor = \ord($value[4] ?? "\0");
        $singleSegment = ($descriptor >> 5) & 1;
        $sizeBytes = [$singleSegment, 2, 4, 8][$descriptor >> 6];

        // magic, descriptor, window (absent in a single segment), dictionary id, content size
        $length = 5 + (1 - $singleSegment) + [0, 1, 2, 4][$descriptor & 0x03] + $sizeBytes;

        // no size field, a header cut short, or an 8-byte field on 32-bit PHP, where unpack('P') throws
        if (0 === $sizeBytes || \strlen($value) < $length || (8 === $sizeBytes && 4 === \PHP_INT_SIZE)) {
            return [$length, null];
        }

        $size = unpack([1 => 'C', 2 => 'v', 4 => 'V', 8 => 'P'][$sizeBytes], $value, $length - $sizeBytes)[1];

        // a two-byte field counts from 256
        return [$length, 2 === $sizeBytes ? $size + 256 : $size];
    }

    /**
     * Where the frame whose blocks start at $position ends, null on a block cut short or invalid (RFC 8878, 3.1.1.2).
     */
    private static function frameLength(string $value, int $position): ?int
    {
        do {
            if ($position + 3 > \strlen($value)) {
                return null;
            }

            // three bytes, little-endian: Last_Block, Block_Type, Block_Size
            $header = \ord($value[$position]) | \ord($value[$position + 1]) << 8 | \ord($value[$position + 2]) << 16;
            $type = ($header >> 1) & 0x03;

            // type 3 is reserved
            if (3 === $type || $header >> 3 > self::BLOCK_SIZE_MAX) {
                return null;
            }

            // an RLE block holds one byte, repeated Block_Size times; the others hold Block_Size bytes
            $position += 3 + (1 === $type ? 1 : $header >> 3);
        } while (0 === ($header & 1));

        // Content_Checksum: four bytes after the last block
        return 0 !== (\ord($value[4]) & 0x04) ? $position + 4 : $position;
    }
}
