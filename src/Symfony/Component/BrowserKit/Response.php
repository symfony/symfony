<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Response
{
    private $content;
    private $status;
    private $headers;

    /**
     * The headers array is a set of key/value pairs. If a header is present multiple times
     * then the value is an array of all the values.
     *
     * @param string $content The content of the response
     * @param int    $status  The response status code
     * @param array  $headers An array of headers
     */
    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    /**
     * Converts the response object to string containing all headers and the response content.
     *
     * @return string The response with headers and content
     */
    public function __toString(): string
    {
        $headers = '';
        foreach ($this->headers as $name => $value) {
            if (\is_string($value)) {
                $headers .= sprintf("%s: %s\n", $name, $value);
            } else {
                foreach ($value as $headerValue) {
                    $headers .= sprintf("%s: %s\n", $name, $headerValue);
                }
            }
        }

        return $headers."\n".$this->content;
    }

    /**
     * Gets the response content.
     *
     * @return string The response content
     */
    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * Gets the response headers.
     *
     * @return array The response headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Gets a response header.
     *
     * @return string|array|null The first header value if $first is true, an array of values otherwise
     */
    public function getHeader(string $header, bool $first = true)
    {
        $normalizedHeader = str_replace('-', '_', strtolower($header));
        foreach ($this->headers as $key => $value) {
            if (str_replace('-', '_', strtolower($key)) === $normalizedHeader) {
                if ($first) {
                    return \is_array($value) ? (\count($value) ? $value[0] : '') : $value;
                }

                return \is_array($value) ? $value : [$value];
            }
        }

        return $first ? null : [];
    }
}
