<?php

namespace Symfony\Component\BrowserKit;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Response object.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Response
{
    protected $content;
    protected $status;
    protected $headers;

    /**
     * Constructor.
     *
     * The headers array is a set of key/value pairs. If a header is present multiple times
     * then the value is an array of all the values.
     *
     * @param string  $content The content of the response
     * @param integer $status  The response status code
     * @param array   $headers An array of headers
     */
    public function __construct($content = '', $status = 200, array $headers = array())
    {
        $this->content = $content;
        $this->status  = $status;
        $this->headers = $headers;
    }

    public function __toString()
    {
        $headers = '';
        foreach ($this->headers as $name => $value) {
            $headers .= sprintf("%s: %s\n", $name, $value);
        }

        return $headers."\n".$this->content;
    }

    /**
     * Gets the response content.
     *
     * @return string The response content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Gets the response status code.
     *
     * @return integer The response status code
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Gets the response headers.
     *
     * @return array The response headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Gets a response header.
     *
     * @param string  $header The header name
     * @param Boolean $first  Whether to return the first value or all header values
     *
     * @return string|array The first header value if $first is true, an array of values otherwise
     */
    public function getHeader($header, $first = true)
    {
        foreach ($this->headers as $key => $value) {
            if (str_replace('-', '_', strtolower($key)) == str_replace('-', '_', strtolower($header))) {
                if ($first) {
                    return is_array($value) ? (count($value) ? $value[0] : '') : $value;
                } else {
                    return is_array($value) ? $value : array($value);
                }
            }
        }

        return $first ? null : array();
    }
}
