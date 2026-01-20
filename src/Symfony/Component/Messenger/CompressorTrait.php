<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Symfony\Component\Messenger\Exception\RuntimeException;

/**
 * @author Santiago San Martin <sanmartindev@gmail.com>
 */
trait CompressorTrait
{
    private const string COMPRESS_HEADER_KEY = 'sf_compress';

    private function compress(string $body, int $level = -1, int $encoding = \FORCE_GZIP): string
    {
        if (!\extension_loaded('zlib')) {
            throw new RuntimeException('Unable to compress the body. Make sure that the "zlib" extension is enabled.');
        }

        return gzencode($body, $level, $encoding);
    }

    private function uncompress(string $body, int $maxLength = 0): string
    {
        if (!\extension_loaded('zlib')) {
            throw new RuntimeException('Unable to uncompress the body. Make sure that the "zlib" extension is enabled.');
        }

        return gzdecode($body, $maxLength);
    }
}
