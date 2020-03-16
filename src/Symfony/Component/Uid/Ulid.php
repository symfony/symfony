<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid;

/**
 * A ULID is lexicographically sortable and contains a 48-bit timestamp and 80-bit of crypto-random entropy.
 *
 * @see https://github.com/ulid/spec
 *
 * @experimental in 5.1
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Ulid extends AbstractUid
{
    private static $time = '';
    private static $rand = [];

    public function __construct(string $ulid = null)
    {
        if (null === $ulid) {
            $this->uid = self::generate();

            return;
        }

        if (!self::isValid($ulid)) {
            throw new \InvalidArgumentException(sprintf('Invalid ULID: "%s".', $ulid));
        }

        $this->uid = strtr($ulid, 'abcdefghjkmnpqrstvwxyz', 'ABCDEFGHJKMNPQRSTVWXYZ');
    }

    public static function isValid(string $ulid): bool
    {
        if (26 !== \strlen($ulid)) {
            return false;
        }

        if (26 !== strspn($ulid, '0123456789ABCDEFGHJKMNPQRSTVWXYZabcdefghjkmnpqrstvwxyz')) {
            return false;
        }

        return $ulid[0] <= '7';
    }

    /**
     * {@inheritdoc}
     */
    public static function fromString(string $ulid): parent
    {
        if (36 === \strlen($ulid) && Uuid::isValid($ulid)) {
            $ulid = Uuid::fromString($ulid)->toBinary();
        } elseif (22 === \strlen($ulid) && 22 === strspn($ulid, BinaryUtil::BASE58[''])) {
            $ulid = BinaryUtil::fromBase($ulid, BinaryUtil::BASE58);
        }

        if (16 !== \strlen($ulid)) {
            return new static($ulid);
        }

        $ulid = bin2hex($ulid);
        $ulid = sprintf('%02s%04s%04s%04s%04s%04s%04s',
            base_convert(substr($ulid, 0, 2), 16, 32),
            base_convert(substr($ulid, 2, 5), 16, 32),
            base_convert(substr($ulid, 7, 5), 16, 32),
            base_convert(substr($ulid, 12, 5), 16, 32),
            base_convert(substr($ulid, 17, 5), 16, 32),
            base_convert(substr($ulid, 22, 5), 16, 32),
            base_convert(substr($ulid, 27, 5), 16, 32)
        );

        return new self(strtr($ulid, 'abcdefghijklmnopqrstuv', 'ABCDEFGHJKMNPQRSTVWXYZ'));
    }

    public function toBinary(): string
    {
        $ulid = strtr($this->uid, 'ABCDEFGHJKMNPQRSTVWXYZ', 'abcdefghijklmnopqrstuv');

        $ulid = sprintf('%02s%05s%05s%05s%05s%05s%05s',
            base_convert(substr($ulid, 0, 2), 32, 16),
            base_convert(substr($ulid, 2, 4), 32, 16),
            base_convert(substr($ulid, 6, 4), 32, 16),
            base_convert(substr($ulid, 10, 4), 32, 16),
            base_convert(substr($ulid, 14, 4), 32, 16),
            base_convert(substr($ulid, 18, 4), 32, 16),
            base_convert(substr($ulid, 22, 4), 32, 16)
        );

        return hex2bin($ulid);
    }

    public function toBase32(): string
    {
        return $this->uid;
    }

    public function getTime(): float
    {
        $time = strtr(substr($this->uid, 0, 10), 'ABCDEFGHJKMNPQRSTVWXYZ', 'abcdefghijklmnopqrstuv');

        if (\PHP_INT_SIZE >= 8) {
            return hexdec(base_convert($time, 32, 16)) / 1000;
        }

        $time = sprintf('%02s%05s%05s',
            base_convert(substr($time, 0, 2), 32, 16),
            base_convert(substr($time, 2, 4), 32, 16),
            base_convert(substr($time, 6, 4), 32, 16)
        );

        return BinaryUtil::toBase(hex2bin($time), BinaryUtil::BASE10) / 1000;
    }

    private static function generate(): string
    {
        $time = microtime(false);
        $time = substr($time, 11).substr($time, 2, 3);

        if ($time !== self::$time) {
            $r = unpack('nr1/nr2/nr3/nr4/nr', random_bytes(10));
            $r['r1'] |= ($r['r'] <<= 4) & 0xF0000;
            $r['r2'] |= ($r['r'] <<= 4) & 0xF0000;
            $r['r3'] |= ($r['r'] <<= 4) & 0xF0000;
            $r['r4'] |= ($r['r'] <<= 4) & 0xF0000;
            unset($r['r']);
            self::$rand = array_values($r);
            self::$time = $time;
        } elseif ([0xFFFFF, 0xFFFFF, 0xFFFFF, 0xFFFFF] === self::$rand) {
            usleep(100);

            return self::generate();
        } else {
            for ($i = 3; $i >= 0 && 0xFFFFF === self::$rand[$i]; --$i) {
                self::$rand[$i] = 0;
            }

            ++self::$rand[$i];
        }

        if (\PHP_INT_SIZE >= 8) {
            $time = base_convert($time, 10, 32);
        } else {
            $time = bin2hex(BinaryUtil::fromBase($time, BinaryUtil::BASE10));
            $time = sprintf('%s%04s%04s',
                base_convert(substr($time, 0, 2), 16, 32),
                base_convert(substr($time, 2, 5), 16, 32),
                base_convert(substr($time, 7, 5), 16, 32)
            );
        }

        return strtr(sprintf('%010s%04s%04s%04s%04s',
            $time,
            base_convert(self::$rand[0], 10, 32),
            base_convert(self::$rand[1], 10, 32),
            base_convert(self::$rand[2], 10, 32),
            base_convert(self::$rand[3], 10, 32)
        ), 'abcdefghijklmnopqrstuv', 'ABCDEFGHJKMNPQRSTVWXYZ');
    }
}
