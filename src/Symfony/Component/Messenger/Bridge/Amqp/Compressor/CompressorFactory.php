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
        if ('gzip' === $mimeContentEncoding) {
            return new Gzip();
        } elseif ('deflate') {
            return new Deflate();
        }

        throw new InvalidArgumentException(sprintf('The MIME content encoding of the message cannot be decompressed "%s".', $mimeContentEncoding));
    }
}
