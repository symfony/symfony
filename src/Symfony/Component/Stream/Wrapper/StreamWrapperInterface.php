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

/**
 * Representation for PHPs native stream wrapper.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @see https://secure.php.net/manual/en/class.streamwrapper.php
 *
 * @property resource|null $context
 *
 * @internal
 */
interface StreamWrapperInterface
{
    /**
     * @return resource
     */
    public function stream_cast(int $cast_as);

    public function stream_close(): void;

    public function stream_eof(): bool;

    public function stream_flush(): bool;

    public function stream_lock(int $operation): bool;

    public function stream_metadata(string $path, int $option, $value): bool;

    public function stream_open(string $path, string $mode, int $options, string &$opened_path): bool;

    public function stream_read(int $count): string;

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool;

    public function stream_set_option(int $option, int $arg1, int $arg2): bool;

    public function stream_stat(): array;

    public function stream_tell(): int;

    public function stream_truncate(int $new_size): bool;

    public function stream_write(string $data): int;
}
