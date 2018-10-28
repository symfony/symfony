<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stream\Wrapper;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Native filesystem stream wrapper.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
class FilesystemStreamWrapper implements FileStreamWrapperInterface
{
    /** @var resource|null */
    public $context;

    private static $protocols = array();
    private static $filesystem = array();
    private $directory;

    public static function register(string $protocol, string $baseDir): void
    {
        self::$protocols[$protocol] = rtrim($baseDir, '/\\');
    }

    final public function __construct()
    {
        if (null === self::$filesystem) {
            self::$filesystem = new Filesystem();
        }
    }

    public function dir_closedir(): bool
    {
        if (null === $this->directory) {
            return false;
        }

        closedir($this->directory);

        return null === error_get_last();
    }

    public function dir_opendir(string $path, int $options): bool
    {
        if (null === $this->context) {
            $this->directory = opendir($this->expand($path)) ?: null;
        } else {
            $this->directory = opendir($this->expand($path), $this->context) ?: null;
        }

        return null !== $this->directory;
    }

    public function dir_readdir()
    {
        return null === $this->directory ? false : readdir($this->directory);
    }

    public function dir_rewinddir(): bool
    {
        return null !== $this->directory && false !== rewinddir($this->directory);
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        try {
            // always recursive, therefor ignores STREAM_MKDIR_RECURSIVE
            self::$filesystem->mkdir($this->expand($path), $mode, $this->context);

            return true;
        } catch (IOException $e) {
            return false;
        }
    }

    public function rename(string $path_from, string $path_to): bool
    {
    }

    public function rmdir(string $path, int $options): bool
    {
    }

    /**
     * @return resource
     */
    public function stream_cast(int $cast_as)
    {
    }

    public function stream_close(): void
    {
    }

    public function stream_eof(): bool
    {
    }

    public function stream_flush(): bool
    {
    }

    public function stream_lock(int $operation): bool
    {
    }

    public function stream_metadata(string $path, int $option, $value): bool
    {
    }

    public function stream_open(string $path, string $mode, int $options, string &$opened_path): bool
    {
    }

    public function stream_read(int $count): string
    {
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
    }

    public function stream_stat(): array
    {
    }

    public function stream_tell(): int
    {
    }

    public function stream_truncate(int $new_size): bool
    {
    }

    public function stream_write(string $data): int
    {
    }

    public function unlink(string $path): bool
    {
    }

    public function url_stat(string $path, int $flags): array
    {
    }

    private function expand(string $path): string
    {
        if (false === ($i = strpos($path, '://')) || null === $baseDir = self::$protocols[substr($path, 0, $i)] ?? null) {
            return $path;
        }

        return $baseDir.\DIRECTORY_SEPARATOR.ltrim(substr($path, $i + 3), '/\\');
    }
}
