<?php

namespace Symfony\Components\BrowserKit;

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
 * @package    Symfony
 * @subpackage Components_BrowserKit
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Response
{
    protected $content;
    protected $status;
    protected $headers;
    protected $cookies;

    /**
     * Constructor.
     *
     * @param string  $content The content of the response
     * @param integer $status  The response status code
     * @param array   $headers An array of headers
     * @param array   $cookies An array of cookies
     */
    public function __construct($content = '', $status = 200, array $headers = array(), array $cookies = array())
    {
        $this->content = $content;
        $this->status  = $status;
        $this->headers = $headers;
        $this->cookies = $cookies;
    }

    public function __toString()
    {
        $headers = '';
        foreach ($this->headers as $name => $value) {
            $headers .= sprintf("%s: %s\n", $name, $value);
        }
        foreach ($this->cookies as $name => $cookie) {
            $headers .= sprintf("Set-Cookie: %s=%s\n", $name, $cookie['value']);
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
     * @param string $header The header name
     *
     * @return string The header value
     */
    public function getHeader($header)
    {
        foreach ($this->headers as $key => $value) {
            if (str_replace('-', '_', strtolower($key)) == str_replace('-', '_', strtolower($header))) {
                return $value;
            }
        }
    }

    /**
     * Gets the response cookies.
     *
     * @return array The response cookies
     */
    public function getCookies()
    {
        return $this->cookies;
    }
}
