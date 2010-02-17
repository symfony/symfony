<?php

namespace Symfony\Components\RequestHandler;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Response is the base implementation of a server response.
 *
 * @package    symfony
 * @subpackage request_handler
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Response implements ResponseInterface
{
  protected $content;
  protected $version;
  protected $statusCode;
  protected $statusText;
  protected $headers;
  protected $cookies;

  static public $statusTexts = array(
    '100' => 'Continue',
    '101' => 'Switching Protocols',
    '200' => 'OK',
    '201' => 'Created',
    '202' => 'Accepted',
    '203' => 'Non-Authoritative Information',
    '204' => 'No Content',
    '205' => 'Reset Content',
    '206' => 'Partial Content',
    '300' => 'Multiple Choices',
    '301' => 'Moved Permanently',
    '302' => 'Found',
    '303' => 'See Other',
    '304' => 'Not Modified',
    '305' => 'Use Proxy',
    '307' => 'Temporary Redirect',
    '400' => 'Bad Request',
    '401' => 'Unauthorized',
    '402' => 'Payment Required',
    '403' => 'Forbidden',
    '404' => 'Not Found',
    '405' => 'Method Not Allowed',
    '406' => 'Not Acceptable',
    '407' => 'Proxy Authentication Required',
    '408' => 'Request Timeout',
    '409' => 'Conflict',
    '410' => 'Gone',
    '411' => 'Length Required',
    '412' => 'Precondition Failed',
    '413' => 'Request Entity Too Large',
    '414' => 'Request-URI Too Long',
    '415' => 'Unsupported Media Type',
    '416' => 'Requested Range Not Satisfiable',
    '417' => 'Expectation Failed',
    '500' => 'Internal Server Error',
    '501' => 'Not Implemented',
    '502' => 'Bad Gateway',
    '503' => 'Service Unavailable',
    '504' => 'Gateway Timeout',
    '505' => 'HTTP Version Not Supported',
  );

  public function __construct($content = '', $status = 200, $headers = array())
  {
    $this->setContent($content);
    $this->setStatusCode($status);
    $this->setProtocolVersion('1.0');
    $this->headers = array();
    foreach ($headers as $name => $value)
    {
      $this->setHeader($name, $value);
    }
    $this->cookies = array();
  }

  public function __toString()
  {
    $this->sendHeaders();

    return (string) $this->getContent();
  }

  /**
   * Sets the response content
   *
   * @param string $content
   */
  public function setContent($content)
  {
    $this->content = $content;

    return $this;
  }

  /**
   * Gets the current response content
   *
   * @return string Content
   */
  public function getContent()
  {
    return $this->content;
  }

  /**
   * Sets the HTTP protocol version (1.0 or 1.1).
   *
   * @param string $version The HTTP protocol version
   */
  public function setProtocolVersion($version)
  {
    $this->version = $version;

    return $this;
  }

  /**
   * Gets the HTTP protocol version.
   *
   * @return string The HTTP protocol version
   */
  public function getProtocolVersion()
  {
    return $this->version;
  }

  /**
   * Sets a cookie.
   *
   * @param  string  $name      HTTP header name
   * @param  string  $value     Value for the cookie
   * @param  string  $expire    Cookie expiration period
   * @param  string  $path      Path
   * @param  string  $domain    Domain name
   * @param  bool    $secure    If secure
   * @param  bool    $httpOnly  If uses only HTTP
   */
  public function setCookie($name, $value, $expire = null, $path = '/', $domain = '', $secure = false, $httpOnly = false)
  {
    if (!is_null($expire))
    {
      if (is_numeric($expire))
      {
        $expire = (int) $expire;
      }
      else
      {
        $expire = strtotime($expire);
        if (false === $expire || -1 == $expire)
        {
          throw new \InvalidArgumentException('The cookie expire parameter is not valid.');
        }
      }
    }

    $this->cookies[$name] = array(
      'name'     => $name,
      'value'    => $value,
      'expire'   => $expire,
      'path'     => $path,
      'domain'   => $domain,
      'secure'   => (Boolean) $secure,
      'httpOnly' => (Boolean) $httpOnly,
    );

    return $this;
  }

  /**
   * Retrieves cookies from the current web response.
   *
   * @return array Cookies
   */
  public function getCookies()
  {
    return $this->cookies;
  }

  /**
   * Sets response status code.
   *
   * @param string $code  HTTP status code
   * @param string $text  HTTP status text
   *
   */
  public function setStatusCode($code, $text = null)
  {
    $this->statusCode = (int) $code;
    if ($this->statusCode < 100 || $this->statusCode > 599)
    {
      throw new \InvalidArgumentException(sprintf('The HTTP status code "%s" is not valid.', $code));
    }

    $this->statusText = false === $text ? '' : (is_null($text) ? self::$statusTexts[$this->statusCode] : $text);

    return $this;
  }

  /**
   * Retrieves status code for the current web response.
   *
   * @return string Status code
   */
  public function getStatusCode()
  {
    return $this->statusCode;
  }

  /**
   * Sets a HTTP header.
   *
   * @param string  $name     HTTP header name
   * @param string  $value    Value (if null, remove the HTTP header)
   * @param bool    $replace  Replace for the value
   *
   */
  public function setHeader($name, $value, $replace = true)
  {
    $name = $this->normalizeHeaderName($name);

    if (is_null($value))
    {
      unset($this->headers[$name]);

      return;
    }

    if (!$replace)
    {
      $current = isset($this->headers[$name]) ? $this->headers[$name] : '';
      $value = ($current ? $current.', ' : '').$value;
    }

    $this->headers[$name] = $value;

    return $this;
  }

  /**
   * Gets HTTP header current value.
   *
   * @param  string $name     HTTP header name
   * @param  string $default  Default value returned if named HTTP header is not found
   *
   * @return array
   */
  public function getHeader($name, $default = null)
  {
    $name = $this->normalizeHeaderName($name);

    return isset($this->headers[$name]) ? $this->headers[$name] : $default;
  }

  /**
   * Checks if the response has given HTTP header.
   *
   * @param  string $name  HTTP header name
   *
   * @return bool
   */
  public function hasHeader($name)
  {
    return array_key_exists($this->normalizeHeaderName($name), $this->headers);
  }

  /**
   * Retrieves HTTP headers from the current web response.
   *
   * @return string HTTP headers
   */
  public function getHeaders()
  {
    return $this->headers;
  }

  /**
   * Sends HTTP headers, including cookies.
   */
  public function sendHeaders()
  {
    if (!$this->hasHeader('Content-Type'))
    {
      $this->setHeader('Content-Type', 'text/html');
    }

    // status
    header(sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->statusText));

    // headers
    foreach ($this->headers as $name => $value)
    {
      header($name.': '.$value);
    }

    // cookies
    foreach ($this->cookies as $cookie)
    {
      setrawcookie($cookie['name'], $cookie['value'], $cookie['expire'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httpOnly']);
    }
  }

  /**
   * Sends content for the current web response.
   */
  public function sendContent()
  {
    echo $this->content;
  }

  /**
   * Sends HTTP headers and content.
   */
  public function send()
  {
    $this->sendHeaders();
    $this->sendContent();
  }

  /**
   * Normalizes a HTTP header name.
   *
   * @param  string $name The HTTP header name
   *
   * @return string The normalized HTTP header name
   */
  protected function normalizeHeaderName($name)
  {
    return strtr(strtolower($name), '_', '-');
  }
}
