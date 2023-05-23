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

class Gzip implements CompressorInterface
{
    public const CONTENT_ENCODING = 'gzip';

    public function compress(mixed $data): string
    {
        return gzencode($data);
    }

    public function decompress(mixed $data): mixed
    {
        if (\function_exists('gzdecode')) {
            return @gzdecode($data) ?: $data;
        }

        return $data;
    }
}
