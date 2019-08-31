<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

/**
 * Handlers can implement this interface to handle multiple messages.
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 *
 * @experimental in 4.3
 */
class RawMessage
{
    private $headers;
    private $data;

    public function __construct(string $data, array $headers = [])
    {
        $this->data = $data;
        $this->headers = $headers;
    }

    /**
     * Get raw data from transport
     *
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Get raw headers from transport
     *
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get content type if exposed in headers
     *
     * @return string|null
     */
    public function getContentType(): ?string
    {
        return $this->headers['Content-Type'] ?? null;
    }

    /**
     * Get message type if exposed in headers
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->headers['type'] ?? null;
    }
}
