<?php

namespace Symfony\Component\HttpFoundation\File\Tests\Fixtures;

class PaddingStreamFilter extends \php_user_filter
{
    const NAME = 'pad';

    const PADDING = '     ';

    public function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data .= self::PADDING;
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
