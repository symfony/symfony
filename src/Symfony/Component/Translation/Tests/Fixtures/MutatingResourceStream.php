<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Fixtures;

use Symfony\Component\Filesystem\Filesystem;

class MutatingResourceStream
{
    public $context;
    public static string $path;
    public static bool $mutated = false;
    private $stream;
    private bool $mutate = false;

    /**
     * Arms the edit for the XML consumer's read while leaving resource hashing reads unchanged.
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->stream = fopen(self::$path, $mode);
        foreach (debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (Filesystem::class === ($frame['class'] ?? null) && 'readFile' === $frame['function']) {
                $this->mutate = true;
            }
        }

        return false !== $this->stream;
    }

    public function stream_read(int $count): string|false
    {
        return fread($this->stream, $count);
    }

    public function stream_eof(): bool
    {
        return feof($this->stream);
    }

    public function stream_tell(): int|false
    {
        return ftell($this->stream);
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return 0 === fseek($this->stream, $offset, $whence);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int, 8: int, 9: int, 10: int, 11: int, 12: int, dev: int, ino: int, mode: int, nlink: int, uid: int, gid: int, rdev: int, size: int, atime: int, mtime: int, ctime: int, blksize: int, blocks: int}|false
     */
    public function stream_stat(): array|false
    {
        return fstat($this->stream);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int, 8: int, 9: int, 10: int, 11: int, 12: int, dev: int, ino: int, mode: int, nlink: int, uid: int, gid: int, rdev: int, size: int, atime: int, mtime: int, ctime: int, blksize: int, blocks: int}|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        return stat(self::$path);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    /**
     * Replaces the file after the XML reader has received all of its previous bytes.
     */
    public function stream_close(): void
    {
        fclose($this->stream);
        if ($this->mutate && !self::$mutated) {
            self::$mutated = true;
            file_put_contents(self::$path, str_replace('v1', 'v2', file_get_contents(self::$path)));
            clearstatcache();
        }
    }
}
