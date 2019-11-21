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
 * Stamp to add a custom header to be used by the transport.
 *
 * @author Alan Poulain <contact@alanpoulain.eu>
 */
final class HeaderStamp implements StampInterface
{
    private $headerName;
    private $headerValue;

    public function __construct(string $headerName, string $headerValue)
    {
        $this->headerName = $headerName;
        $this->headerValue = $headerValue;
    }

    public function getHeaderName(): string
    {
        return $this->headerName;
    }

    public function getHeaderValue(): string
    {
        return $this->headerValue;
    }
}
