<?php

namespace Symfony\Components\HttpKernel\Test;

use Symfony\Components\HttpKernel\Request;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * RequestTester implements tests for the Request object.
 *
 * @package    Symfony
 * @subpackage Components_HttpKernel
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RequestTester extends Tester
{
    protected $request;

    /**
     * Constructor.
     *
     * @param Symfony\Components\HttpKernel\Request $request A Request instance
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Asserts the value of a request parameter.
     *
     * @param string $key
     * @param string $value
     */
    public function assertParameter($key, $value)
    {
        $this->test->assertEquals($value, $this->request->get($key), sprintf('Request parameter "%s" is "%s"', $key, $value));
    }

    /**
     * Asserts the value of a request query ($_GET).
     *
     * @param string $key
     * @param string $value
     */
    public function assertQueryParameter($key, $value)
    {
        $this->test->assertEquals($value, $this->request->query->get($key), sprintf('Request query parameter "%s" is "%s"', $key, $value));
    }

    /**
     * Asserts the value of a request request ($_POST).
     *
     * @param string $key
     * @param string $value
     */
    public function assertRequestParameter($key, $value)
    {
        $this->test->assertEquals($value, $this->request->request->get($key), sprintf('Request request parameter "%s" is "%s"', $key, $value));
    }

    /**
     * Asserts the value of a request path.
     *
     * @param string $key
     * @param string $value
     */
    public function assertPathParameter($key, $value)
    {
        $this->test->assertEquals($value, $this->request->path->get($key), sprintf('Request path parameter "%s" is "%s"', $key, $value));
    }

    /**
     * Asserts that the request is in the given format.
     *
     * @param string $format The request format
     */
    public function assertFormat($format)
    {
        $this->test->assertEquals($format, $this->request->getRequestFormat(), sprintf('Request format is "%s"', $format));
    }

    /**
     * Asserts if the current HTTP method matches the given one.
     *
     * @param string $method The HTTP method name
     */
    public function assertMethod($method)
    {
        $this->test->assertEquals(strtolower($method), strtolower($this->request->getMethod()), sprintf('Request method is "%s"', strtoupper($method)));
    }

    /**
     * Asserts if a cookie exists.
     *
     * @param string $name The cookie name
     */
    public function assertCookieExists($name)
    {
        $this->test->assertTrue(false === $this->request->cookies->get($name, false), sprintf('Cookie "%s" exists', $name));
    }

    /**
     * Asserts if a cookie does not exist.
     *
     * @param string $name The cookie name
     */
    public function assertNotCookieExists($name)
    {
        $this->test->assertFalse(false === $this->request->cookies->get($name, false), sprintf('Cookie "%s" does not exist', $name));
    }

    /**
     * Asserts the value of a cookie.
     *
     * @param string $name  The cookie name
     * @param string $value The expected value
     */
    public function assertCookieEquals($name, $value)
    {
        if (!$this->request->cookies->has($name)) {
            return $this->test->fail(sprintf('Cookie "%s" does not exist.', $name));
        }

        $this->test->is($this->request->cookies->get($name), $value, sprintf('Cookie "%s" content is "%s"', $name, $value));
    }

    /**
     * Asserts that the value of a cookie matches a regexp.
     *
     * @param string $name   The cookie name
     * @param string $regexp A regexp
     */
    public function assertCookieRegExp($name, $regexp)
    {
        if (!$this->request->cookies->has($name)) {
            return $this->test->fail(sprintf('cookie "%s" does not exist.', $name));
        }

        $this->test->assertRegExp($this->request->cookies->get($name), $value, sprintf('Cookie "%s" content matches regex "%s"', $name, $value));
    }

    /**
     * Asserts that the value of a cookie does not match a regexp.
     *
     * @param string $name   The cookie name
     * @param string $regexp A regexp
     */
    public function assertNotCookieRegExp($name, $regexp)
    {
        if (!$this->request->cookies->has($name)) {
            return $this->test->fail(sprintf('Cookie "%s" does not exist.', $name));
        }

        $this->test->assertNotRegExp($this->request->cookies->get($name), $value, sprintf('Cookie "%s" content does not match regex "%s"', $name, $value));
    }
}
