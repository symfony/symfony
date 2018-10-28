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
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
interface FileStreamWrapperInterface extends StreamWrapperInterface
{
    public function dir_closedir(): bool;

    public function dir_opendir(string $path, int $options): bool;

    public function dir_readdir();

    public function dir_rewinddir(): bool;

    public function mkdir(string $path, int $mode, int $options): bool;

    public function rename(string $path_from, string $path_to): bool;

    public function rmdir(string $path, int $options): bool;

    public function unlink(string $path): bool;

    public function url_stat(string $path, int $flags): array;
}
