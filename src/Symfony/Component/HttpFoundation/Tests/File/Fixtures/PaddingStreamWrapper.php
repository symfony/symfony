<?php

namespace Symfony\Component\HttpFoundation\File\Tests\Fixtures;

class PaddingStreamWrapper
{
    protected $file;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->file = fopen('php://temp', 'r+');

        $path_parts = explode('://', $path);
        $file_contents = end($path_parts);
        fwrite($this->file, $file_contents);
        fseek($this->file, 0);

        stream_filter_register(PaddingStreamFilter::NAME, '\Symfony\Component\HttpFoundation\File\Tests\Fixtures\PaddingStreamFilter');
        stream_filter_append($this->file, PaddingStreamFilter::NAME, STREAM_FILTER_READ);

        return (bool) $this->file;
    }

    public function stream_read($count)
    {
        return fread($this->file, $count);
    }

    public function stream_write($data)
    {
        return fwrite($this->file, $data);
    }

    public function stream_tell()
    {
        return ftell($this->file);
    }

    public function stream_eof()
    {
        return feof($this->file);
    }

    public function stream_seek($offset, $whence)
    {
        return fseek($this->file, $offset, $whence);
    }

    public function stream_stat()
    {
        return fstat($this->file);
    }

    public function url_stat($path, $flags)
    {
        $file = fopen($path, 'r');
        $stat = fstat($file);
        fclose($file);

        return $stat;
    }
}
