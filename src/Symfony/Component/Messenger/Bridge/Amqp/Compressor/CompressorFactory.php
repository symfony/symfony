<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Compressor;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;

class CompressorFactory
{
    public static function createCompressor(string $mimeContentEncoding)
    {
        return match ($mimeContentEncoding) {
            Gzip::CONTENT_ENCODING => new Gzip(),
            Deflate::CONTENT_ENCODING => new Deflate(),
            default => throw new InvalidArgumentException(sprintf('The MIME content encoding of the message cannot be decompressed "%s".', $mimeContentEncoding)),
        };
    }
}
