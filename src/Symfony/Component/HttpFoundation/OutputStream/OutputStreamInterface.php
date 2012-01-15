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
 * OutputStreamInterface defines how streaming responses
 * send data to the client.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
interface OutputStreamInterface
{
    /**
     * Write some data to the stream.
     *
     * @param string $data The data to write
     */
    function write($data);

    /**
     * Close the stream.
     *
     * This should be called after the sending of data
     * has been completed. In case of persistent connections,
     * it is never called.
     */
    function close();
}
