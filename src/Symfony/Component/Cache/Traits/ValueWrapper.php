<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A short namespace-less class to serialize items with metadata.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class ©
{
    private const EXPIRY_OFFSET = 1648206727;
    private const INT32_MAX = 2147483647;

    public readonly mixed $value;
    public readonly array $metadata;

    public function __construct(mixed $value, array $metadata)
    {
        $this->value = $value;
        $this->metadata = $metadata;
    }

    public function __serialize(): array
    {
        // pack 31-bits ctime into 14bits
        $c = $this->metadata['ctime'] ?? 0;
        $c = match (true) {
            $c > self::INT32_MAX - 2 => self::INT32_MAX,
            $c > 0 => 1 + $c,
            default => 1,
        };
        $e = 0;
        while (!(0x40000000 & $c)) {
            $c <<= 1;
            ++$e;
        }
        $c = (0x7FE0 & ($c >> 16)) | $e;

        $pack = pack('Vn', (int) (0.1 + ($this->metadata['expiry'] ?: self::INT32_MAX + self::EXPIRY_OFFSET) - self::EXPIRY_OFFSET), $c);

        if (isset($this->metadata['tags'])) {
            $pack[4] = $pack[4] | "\x80";
        }

        return [$pack => $this->value] + ($this->metadata['tags'] ?? []);
    }

    public function __unserialize(array $data)
    {
        $pack = array_key_first($data);
        $this->value = $data[$pack];

        if ($hasTags = "\x80" === ($pack[4] & "\x80")) {
            unset($data[$pack]);
            $pack[4] = $pack[4] & "\x7F";
        }

        $metadata = unpack('Vexpiry/nctime', $pack);
        $metadata['expiry'] += self::EXPIRY_OFFSET;

        if (!$metadata['ctime'] = ((0x4000 | $metadata['ctime']) << 16 >> (0x1F & $metadata['ctime'])) - 1) {
            unset($metadata['ctime']);
        }

        if ($hasTags) {
            $metadata['tags'] = $data;
        }

        $this->metadata = $metadata;
    }
}
