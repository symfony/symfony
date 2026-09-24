<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests\Fixtures;

/**
 * A seekable read-only stream over a string that counts the bytes read from it, to be used with stream_wrapper_register().
 */
final class CountingStream
{
    public static string $data = '';
    public static int $bytesRead = 0;

    /** @var resource|null */
    public $context;

    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$data, $this->position, $count);
        $this->position += \strlen($chunk);
        self::$bytesRead += \strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen(self::$data);
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        $this->position = match ($whence) {
            \SEEK_CUR => $this->position + $offset,
            \SEEK_END => \strlen(self::$data) + $offset,
            default => $offset,
        };

        return true;
    }

    public function stream_stat(): array
    {
        return ['size' => \strlen(self::$data)];
    }
}
