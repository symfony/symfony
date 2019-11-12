<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Stamp used to identify that client wants a response.
 * Client gets a response from a handler via this stamp.
 */
class ReplyStamp implements StampInterface
{
    /**
     * @var string
     */
    private $response;

    /**
     * @param string $response
     */
    public function setResponse(string $response): void
    {
        $this->response = $response;
    }

    /**
     * @return string
     */
    public function getResponse(): string
    {
        return $this->response;
    }
}
