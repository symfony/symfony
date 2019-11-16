<?php

namespace Symfony\Component\Messenger\Transport\Semaphore\Util;

final class PlatformUtil
{
    public static function isWindows(): bool
    {
        return 'WIN' === strtoupper(substr(PHP_OS, 0, 3));
    }
}
