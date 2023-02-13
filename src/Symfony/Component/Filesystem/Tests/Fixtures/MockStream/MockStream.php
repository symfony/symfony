<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Tests\Fixtures\MockStream;

/**
 * Mock stream class to be used with stream_wrapper_register.
 * stream_wrapper_register('mock', 'Symfony\Component\Filesystem\Tests\Fixtures\MockStream\MockStream').
 */
class MockStream
{
    public $context;

    /**
     * Opens file or URL.
     *
     * @param string      $path        Specifies the URL that was passed to the original function
     * @param string      $mode        The mode used to open the file, as detailed for fopen()
     * @param int         $options     Holds additional flags set by the streams API
     * @param string|null $opened_path If the path is opened successfully, and STREAM_USE_PATH is set in options,
     *                                 opened_path should be set to the full path of the file/resource that was actually opened
     */
    public function stream_open(string $path, string $mode, int $options, string &$opened_path = null): bool
    {
        return true;
    }

    /**
     * @param string $path  The file path or URL to stat
     * @param int    $flags Holds additional flags set by the streams API
     */
    public function url_stat(string $path, int $flags): array
    {
        return [];
    }
}
