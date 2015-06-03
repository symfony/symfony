<?php

/**
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien@symfony.com>.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This class is based on VariableStream from the PHP Manual, which is licenced
 * under Creative Commons Attribution 3.0 Licence copyright (c) the PHP
 * Documentation Group
 *
 * @url http://php.net/manual/en/stream.streamwrapper.example-1.php
 * @url http://php.net/license/
 * @url http://creativecommons.org/licenses/by/3.0/legalcode
 */
namespace MockStream;

/**
 * Mock stream class to be used with stream_wrapper_register.
 *
 * stream_wrapper_register('mock', 'MockStream\MockStream')
 */
class MockStream {
    private $str_overloaded;
    private $content;
    private $position;
    private $atime;
    private $mtime;
    private $ctime;
    private $path;

    /**
     * Opens file or URL.
     *
     * @param string $path        Specifies the URL that was passed to the original function
     * @param string $mode        The mode used to open the file, as detailed for fopen()
     * @param int    $options     Holds additional flags set by the streams API
     * @param string $opened_path If the path is opened successfully, and STREAM_USE_PATH is set in options, opened_path should be set to the full path of the file/resource that was actually opened
     *
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path) {
        // Is mbstring.func_overload applied to string functions (bit 2 set)
        $this->str_overloaded = (bool) (ini_get('mbstring.func_overload') & (1 << 2));
        $this->path = $path;
        $this->content = '';
        $this->position = 0;
        $this->atime = 0;
        $this->mtime = 0;

        return true;
    }

    /**
     * Read from stream.
     *
     * @param int $count How many bytes of data from the current position should be returned
     *
     * @return string The data
     */
    public function stream_read($count) {
        $ret = $this->substr($this->varname, $this->position, $count);
        $this->position += $this->strlen($ret);
        $this->atime = time();

        return $ret;
    }

    /**
     * Write to stream.
     *
     * @param string $data Data to write to the stream
     *
     * @return int Number of bytes that were successfully stored, or 0 if none could be stored
     */
    public function stream_write($data) {
        $left = $this->substr($this->content, 0, $this->position);
        $right = $this->substr($this->content, $this->position + $this->strlen($data));
        $this->content = $left.$data.$right;
        $this->position += $this->strlen($data);
        $this->mtime = time();
        $this->ctime = time();

        return $this->strlen($data);
    }

    /**
     * Retrieve the current position of a stream.
     *
     * @return int The current position of the stream
     */
    public function stream_tell() {
        return $this->position;
    }

    /**
     * Tests for end-of-file on a file pointer.
     *
     * @return bool Return true if the read/write position is at the end of the stream and if no more data is available to be read, or false otherwise
     */
    public function stream_eof() {
        return $this->position >= $this->strlen($this->content);
    }

    /**
     * Seeks to specific location in a stream.
     *
     * @param string $offset The stream offset to seek to
     * @param int    $whence Set position based on value
     *
     * @return bool Return true if the position was updated, false otherwise
     */
    public function stream_seek($offset, $whence) {
        switch ($whence) {
            case SEEK_SET:
                if ($offset < $this->strlen($this->content) && 0 <= $offset) {
                     $this->position = $offset;

                     return true;
                }
                break;

            case SEEK_CUR:
                if (0 <= $offset) {
                     $this->position += $offset;

                     return true;
                }
                break;

            case SEEK_END:
                if (0 <= $this->strlen($this->content) + $offset) {
                     $this->position = $this->strlen($this->content) + $offset;

                     return true;
                }
                break;
        }

        return false;
    }

    /**
     * Change stream options, only touch is supported.
     *
     * @param string $path   The file path or URL to set metadata
     * @param array  $option
     * @param array  $value  Additional arguments for the option
     *
     * @return bool Return true on success or fale on failure or if option is not implemented
     */
    public function stream_metadata($path, $option, $value) {
        if (STREAM_META_TOUCH === $option) {
            $now = array_key_exists(0, $value) ? $value[0] : time();
            $this->atime = array_key_exists(1, $value) ? $value[1] : $now;
            $this->mtime = $now;
            $this->ctime = $now;

            return true;
        }

        return false;
    }

    /**
     * Retrieve information about a stream.
     *
     * @return array Stream stats
     */
    public function stream_stat() {
        return array(
            'dev' => 0,
            'ino' => 0,
            'mode' => 33188, // 100644
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => $this->strlen($this->content),
            'atime' => $this->atime,
            'mtime' => $this->mtime,
            'ctime' => $this->ctime,
            'blksize' => 4096,
            'blocks' => 8,
        );
    }

    /**
     * Retrieve information about a url, added as called by PHP's builtin functions.
     *
     * @param string $path  The file path or URL to stat
     * @param array  $flags Holds additional flags set by the streams API
     *
     * @return array File stats
     */
    public function url_stat($path, $flags) {
        return $this->stream_stat();
    }

    /**
     * Returns the number of bytes of the given string even when strlen is overloaded to mb_strlen.
     *
     * @param string $string The string being measured for bytes
     *
     * @return int The number of bytes in the string on success, and 0 if the string is empty
     */
    protected function strlen($string) {
        return function_exists('mb_strlen') && $this->str_overloaded ? mb_strlen($string, '8bit') : strlen($string);
    }

    /**
     * Returns the portion of string specified by the start and length parameters even when substr is overloaded to mb_substr.
     *
     * @param string $string The input string which must be one character or longer
     * @param start  $int    Starting position in bytes
     * @param length $int    Length in bytes which if omitted or NULL is passed, extracts all bytes to the end of the string
     *
     * @return string
     */
    protected function substr($string, $start, $length = null) {
        return function_exists('mb_substr') && $this->str_overloaded ? mb_substr($string, $start, $length, '8bit') : substr($string, $start, $length);
    }

}
