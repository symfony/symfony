<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Util;

/**
 * @internal
 */
class GzipStreamWrapper
{
    /** @var resource|null */
    public $context;

    /** @var resource */
    private $handle;
    private string $path;

    public static function require(string $path): array
    {
        if (!\extension_loaded('zlib')) {
            throw new \LogicException(\sprintf('The "zlib" extension is required to load the "%s/%s" map, please enable it in your php.ini file.', basename(\dirname($path)), basename($path)));
        }

        if (!\function_exists('opcache_is_script_cached') || !@opcache_is_script_cached($path)) {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }

        return require $path;
    }

    public function stream_open(string $path, string $mode): bool
    {
        stream_wrapper_restore('file');
        $this->path = $path;

        return false !== $this->handle = fopen('compress.zlib://'.$path, $mode);
    }

    public function stream_read(int $count): string|false
    {
        return fread($this->handle, $count);
    }

    public function stream_eof(): bool
    {
        return feof($this->handle);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return match ($option) {
            \STREAM_OPTION_BLOCKING => stream_set_blocking($this->handle, $arg1),
            \STREAM_OPTION_READ_TIMEOUT => stream_set_timeout($this->handle, $arg1, $arg2),
            \STREAM_OPTION_WRITE_BUFFER => 0 === stream_set_write_buffer($this->handle, $arg2),
            default => false,
        };
    }

    public function stream_stat(): array|false
    {
        if (!$stat = stat($this->path)) {
            return false;
        }

        $h = fopen($this->path, 'r');
        fseek($h, -4, \SEEK_END);
        $size = unpack('V', fread($h, 4));
        fclose($h);

        $stat[7] = $stat['size'] = end($size);

        return $stat;
    }
}
