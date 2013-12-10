<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * SocketResponse represents a HTTP response whose content is read from a socket or file.
 *
 * A SocketRepsonse uses a socket handle (or file descriptor) for its content.
 *
 * The socket handle needs to be readable, e.g. $handle = fopen("example.txt", "r");
 *
 * @author Sascha Schimke <sascha.schimke@postcon.de>
 */
class SocketResponse extends Response
{
    private $handle;
    private $close;
    private $sent;

    /**
     * Constructor
     *
     * @param resource $handle  A readable socket handle (or file descriptor)
     * @param int      $status  The response status code
     * @param array    $headers An array of response headers
     * @param bool     $close   Close socket handle after sending content
     */
    public function __construct($handle = null, $status = 200, $headers = array(), $close = true)
    {
        parent::__construct(null, $status, $headers);

        if (null !== $handle) {
            $this->setHandle($handle);
        }

        $this->close = (bool) $close;

        $this->sent = false;
    }

    /**
     * Factory method for chainability
     *
     * @param resource $handle  A readable socket handle (or file descriptor)
     * @param int      $status  The response status code
     * @param array    $headers An array of response headers
     * @param bool     $close   Close socket handle after sending content
     *
     * @return SocketResponse
     */
    public static function create($handle = null, $status = 200, $headers = array(), $close = true)
    {
        return new static($handle, $status, $headers, $close);
    }

    /**
     * Sets the socket handle associated with this Response.
     *
     * @param mixed $handle A readable socket handle (or file descriptor)
     *
     * @throws \LogicException
     */
    public function setHandle($handle)
    {
        if (!is_resource($handle)) {
            throw new \LogicException("The Response stream handle must be a valid PHP resource.");
        }

        $this->handle = $handle;
    }

    /**
     * {@inheritdoc}
     *
     * This method only sends the content once.
     */
    public function sendContent()
    {
        if ($this->sent) {
            return;
        }

        $this->sent = true;

        if (null === $this->handle) {
            throw new \LogicException("The Response stream handle must not be null.");
        }

        fpassthru($this->handle);

        if ($this->close) {
            fclose($this->handle);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException when the content is not null
     */
    public function setContent($content)
    {
        if (null !== $content) {
            throw new \LogicException("The content cannot be set on a SocketResponse instance.");
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return false
     */
    public function getContent()
    {
        return false;
    }
}
