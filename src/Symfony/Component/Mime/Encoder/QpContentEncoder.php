<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Encoder;

/**
 * @author Lars Strojny
 */
final class QpContentEncoder implements ContentEncoderInterface
{
    public function encodeByteStream($stream, int $maxLineLength = 0): iterable
    {
        if (!\is_resource($stream)) {
            throw new \TypeError(\sprintf('Method "%s" takes a stream as a first argument.', __METHOD__));
        }

        // we don't use PHP stream filters here as the content should be small enough
        yield $this->encodeString(stream_get_contents($stream), 'utf-8', 0, $maxLineLength);
    }

    public function getName(): string
    {
        return 'quoted-printable';
    }

    public function encodeString(string $string, ?string $charset = 'utf-8', int $firstLineOffset = 0, int $maxLineLength = 0): string
    {
        return $this->standardize(quoted_printable_encode($string));
    }

    /**
     * Make sure CRLF is correct and HT/SPACE are in valid places.
     */
    private function standardize(string $string): string
    {
        // quoted_printable_encode() keeps CRLF as is and encodes tabs, so only the encoded CR and LF are left
        // to transform into CRLF, after encoding the space that precedes them
        $string = preg_replace(['/ =0[AD]/', '/=0[AD]/'], ["=20\r\n", "\r\n"], $string);

        return match ($string[-1] ?? '') {
            "\x09" => substr_replace($string, '=09', -1),
            "\x20" => substr_replace($string, '=20', -1),
            default => $string,
        };
    }
}
