<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Resource;

/** @internal */
final class ImageMetadata
{
    public static function orientation(string $data): int
    {
        if (!str_starts_with($data, "\xff\xd8")) {
            return 1;
        }
        $length = \strlen($data);
        // The segments before the image data, until the APP1 one of the EXIF data
        for ($at = 2; $at + 4 <= $length && "\xff" === $data[$at];) {
            $marker = \ord($data[$at + 1]);
            if (0xDA === $marker || 0xD9 === $marker) {
                break;
            }
            $size = unpack('n', $data, $at + 2)[1];
            if (0xE1 === $marker && 'Exif' === substr($data, $at + 4, 4)) {
                $tiff = $at + 10;
                $order = substr($data, $tiff, 2);
                if ('II' !== $order && 'MM' !== $order) {
                    return 1;
                }
                $short = 'II' === $order ? 'v' : 'n';
                $long = 'II' === $order ? 'V' : 'N';
                if ($tiff + 8 > $length) {
                    return 1;
                }
                $ifd = $tiff + unpack($long, $data, $tiff + 4)[1];
                if ($ifd + 2 > $length) {
                    return 1;
                }
                $count = unpack($short, $data, $ifd)[1];
                for ($i = 0; $i < $count && $ifd + 2 + 12 * ($i + 1) <= $length; ++$i) {
                    $entry = $ifd + 2 + 12 * $i;
                    if (0x112 === unpack($short, $data, $entry)[1]) {
                        $value = unpack($short, $data, $entry + 8)[1];

                        return $value >= 1 && $value <= 8 ? $value : 1;
                    }
                }

                return 1;
            }
            $at += 2 + $size;
        }

        return 1;
    }
}
