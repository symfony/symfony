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

class Deflate implements CompressorInterface
{
    public const CONTENT_ENCODING = 'deflate';

    public function compress(mixed $data): string
    {
        return gzdeflate($data);
    }

    public function decompress(mixed $data): mixed
    {
        if (\function_exists('gzinflate')) {
            return @gzinflate($data) ?: $data;
        }

        return $data;
    }
}
