<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\OutputStream;

/**
 * StreamOutputStream is an implementation of the OutputStreamInterface
 * that uses any writable PHP stream as a target.
 *
 * Example usage:
 *
 * $stream = fopen('php://output');
 * $output = new StreamOutputStream($stream, 'w');
 * $output->write('foo');
 * $output->write('bar');
 * $output->close();
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class StreamOutputStream implements OutputStreamInterface
{
    private $stream;

    /**
     * @param resource $stream A valid stream resource, such as the return value of
     *                     a fopen() call.
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('The supplied stream is invalid.');
        }

        $this->stream = $stream;
    }

    /**
     * @{inheritdoc}
     */
    public function write($data)
    {
        fwrite($this->stream, $data);
    }

    /**
     * @{inheritdoc}
     */
    public function close()
    {
        fclose($this->stream);
    }

    /**
     * Static factory method
     */
    static public function create($filename)
    {
        $stream = fopen($filename, 'w');
        return new static($stream);
    }
}
