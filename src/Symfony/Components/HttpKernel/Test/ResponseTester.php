<?php

namespace Symfony\Components\HttpKernel\Test;

use Symfony\Components\HttpKernel\Response;
use Symfony\Components\DomCrawler\Crawler;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * ResponseTester implements tests for the Response object.
 *
 * @package    Symfony
 * @subpackage Components_HttpKernel
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ResponseTester extends Tester
{
    protected $response;
    protected $crawler;

    /**
     * Constructor.
     *
     * @param Symfony\Components\HttpKernel\Response $response A Response instance
     */
    public function __construct(Response $response)
    {
        $this->response = $response;

        if (class_exists('Symfony\Components\DomCrawler\Crawler')) {
            $this->crawler = new Crawler();
            $this->crawler->addContent($this->response->getContent(), $this->response->headers->get('Content-Type'));
        }
    }

    /**
     * Asserts that the response matches a given CSS selector.
     *
     * @param string $selector  A CSS selector
     * @param array  $arguments An array of attributes to extract
     * @param array  $expected  The expected values for the attributes
     */
    public function assertSelectEquals($selector, $arguments, $expected)
    {
        if (null === $this->crawler) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException(sprintf('Unable to use %s() as the Symfony DomCrawler does not seem to be installed.', __METHOD__));
            // @codeCoverageIgnoreEnd
        }

        $this->test->assertEquals($expected, $this->crawler->filter($selector)->extract($arguments));
    }

    /**
     * Asserts that the response matches a given CSS selector n times.
     *
     * @param string  $selector A CSS selector
     * @param integer $count   The number of times the CSS selector must match
     */
    public function assertSelectCount($selector, $count)
    {
        if (null === $this->crawler) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException(sprintf('Unable to use %s() as the Symfony DomCrawler does not seem to be installed.', __METHOD__));
            // @codeCoverageIgnoreEnd
        }

        $this->test->assertEquals($count, $this->crawler->filter($selector)->count(), sprintf('response selector "%s" matches "%s" times', $selector, $count));
    }

    /**
     * Asserts that the response matches a given CSS selector.
     *
     * @param string $selector The CSS selector
     */
    public function assertSelectExists($selector)
    {
        if (null === $this->crawler) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException(sprintf('Unable to use %s() as the Symfony DomCrawler does not seem to be installed.', __METHOD__));
            // @codeCoverageIgnoreEnd
        }

        $this->test->assertTrue($this->crawler->filter($selector)->count() > 0, sprintf('response selector "%s" exists', $selector));
    }

    /**
     * Asserts that the response does not match a given CSS selector.
     *
     * @param string $selector The CSS selector
     */
    public function assertNotSelectExists($selector)
    {
        if (null === $this->crawler) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException(sprintf('Unable to use %s() as the Symfony DomCrawler does not seem to be installed.', __METHOD__));
            // @codeCoverageIgnoreEnd
        }

        $this->test->assertTrue($this->crawler->selectCss($selector)->count() == 0, sprintf('Response selector "%s" does not exist', $selector));
    }

    /**
     * Asserts the a response header has the given value.
     *
     * @param string $key   The header name
     * @param string $value The header value
     */
    public function assertHeaderEquals($key, $value)
    {
        $headers = explode(', ', $this->response->headers->get($key));
        foreach ($headers as $header) {
            if ($header == $value) {
                return $this->test->pass(sprintf('Response header "%s" is "%s" (%s)', $key, $value, $this->response->headers->get($key)));
            }
        }

        $this->test->fail(sprintf('Response header "%s" matches "%s" (%s)', $key, $value, $this->response->headers->get($key)));
    }

    /**
     * Asserts the a response header has not the given value.
     *
     * @param string $key   The header name
     * @param string $value The header value
     */
    public function assertNotHeaderEquals($key, $value)
    {
        $headers = explode(', ', $this->response->headers->get($key));
        foreach ($headers as $header) {
            if ($header == $value) {
                return $this->test->fail(sprintf('Response header "%s" is not "%s" (%s)', $key, $value, $this->response->headers->get($key)));
            }
        }

        $this->test->pass(sprintf('Response header "%s" does not match "%s" (%s)', $key, $value, $this->response->headers->get($key)));
    }

    /**
     * Asserts the response header matches the given regexp.
     *
     * @param string $key   The header name
     * @param string $regex A regexp
     */
    public function assertHeaderRegExp($key, $regex)
    {
        $headers = explode(', ', $this->response->headers->get($key));
        foreach ($headers as $header) {
            if (preg_match($regex, $header)) {
                return $this->test->pass(sprintf('Response header "%s" matches "%s" (%s)', $key, $value, $this->response->headers->get($key)));
            }
        }

        return $this->test->fail(sprintf('Response header "%s" matches "%s" (%s)', $key, $value, $this->response->headers->get($key)));
    }

    /**
     * Asserts the response header does not match the given regexp.
     *
     * @param string $key   The header name
     * @param string $regex A regexp
     */
    public function assertNotHeaderRegExp($key, $regex)
    {
        $headers = explode(', ', $this->response->headers->get($key));
        foreach ($headers as $header) {
            if (!preg_match($regex, $header)) {
                return $this->test->pass(sprintf('Response header "%s" matches "%s" (%s)', $key, $value, $this->response->headers->get($key)));
            }
        }

        return $this->test->fail(sprintf('Response header "%s" matches "%s" (%s)', $key, $value, $this->response->headers->get($key)));
    }

    /**
     * Asserts if a cookie was set with the given value and attributes.
     *
     * @param  string $name       The cookie name
     * @param  string $value      The cookie value
     * @param  array  $attributes Other cookie attributes to check (expires, path, domain, etc)
     */
    public function assertCookie($name, $value = null, $attributes = array())
    {
        foreach ($this->response->getCookies() as $cookie) {
            if ($name == $cookie['name']) {
                if (null === $value) {
                    $this->test->pass(sprintf('Response sets cookie "%s"', $name));
                } else {
                    $this->test->assertTrue($value == $cookie['value'], sprintf('Response sets cookie "%s" to "%s"', $name, $value));
                }

                foreach ($attributes as $attributeName => $attributeValue) {
                    if (!array_key_exists($attributeName, $cookie)) {
                        throw new \LogicException(sprintf('The cookie attribute "%s" is not valid.', $attributeName));
                    }

                    $this->test->assertEquals($attributeValue, $cookie[$attributeName], sprintf('Attribute "%s" of cookie "%s" is "%s"', $attributeName, $name, $attributeValue));
                }

                return;
            }
        }

        $this->test->fail(sprintf('response sets cookie "%s"', $name));
    }

    /**
     * Asserts that the response content matches a regexp.
     *
     * @param string The regexp
     */
    public function assertRegExp($regex)
    {
        $this->test->assertRegExp($regex, $this->response->getContent(), sprintf('Response content matches regex "%s"', $regex));
    }

    /**
     * Asserts that the response content does not match a regexp.
     *
     * @param string The regexp
     */
    public function assertNotRegExp($regex)
    {
        $this->test->assertNotRegExp($regex, $this->response->getContent(), sprintf('Response content does not match regex "%s"', substr($regex, 1)));
    }

    /**
     * Asserts the response status code.
     *
     * @param string $statusCode Status code to check, default 200
     */
    public function assertStatusCode($statusCode = 200)
    {
        $this->test->assertEquals($statusCode, $this->response->getStatusCode(), sprintf('Status code is "%s"', $statusCode));
    }

    /**
     * Asserts that the response status code is informational.
     */
    public function assertStatusCodeInformational()
    {
        $this->test->assertTrue($this->response->getStatusCode() >= 100 && $this->response->getStatusCode() < 200, 'Status code is informational');
    }

    /**
     * Asserts that the response status code is successful.
     */
    public function assertStatusCodeSuccessful()
    {
        $this->test->assertTrue($this->response->getStatusCode() >= 200 && $this->response->getStatusCode() < 300, 'Status code is successful');
    }

    /**
     * Asserts that the response status code is a redirection.
     */
    public function assertStatusCodeRedirection()
    {
        $this->test->assertTrue($this->response->getStatusCode() >= 300 && $this->response->getStatusCode() < 400, 'Status code is successful');
    }

    /**
     * Asserts that the response status code is a client error.
     */
    public function assertStatusCodeClientError()
    {
        $this->test->assertTrue($this->response->getStatusCode() >= 400 && $this->response->getStatusCode() < 500, 'Status code is a client error');
    }

    /**
     * Asserts that the response status code is a server error.
     */
    public function assertStatusCodeServerError()
    {
        $this->test->assertTrue($this->response->getStatusCode() >= 500 && $this->response->getStatusCode() < 600, 'Status code is a server error');
    }

    /**
     * Asserts that the response status code is ok.
     */
    public function assertStatusCodeOk()
    {
        $this->test->assertEquals(200, $this->response->getStatusCode(), 'Status code is ok');
    }

    /**
     * Asserts that the response status code is forbidden.
     */
    public function assertStatusCodeForbidden()
    {
        $this->test->assertEquals(403, $this->response->getStatusCode(), 'Status code is forbidden');
    }

    /**
     * Asserts that the response status code is not found.
     */
    public function assertStatusCodeNotFound()
    {
        $this->test->assertEquals(404, $this->response->getStatusCode(), 'Status code is not found');
    }

    /**
     * Asserts that the response status code is a redirect.
     *
     * @param string $location The redirection location
     */
    public function assertStatusCodeRedirect($location = null)
    {
        $this->test->assertTrue(in_array($this->response->getStatusCode(), array(301, 302, 303, 307)), 'Status code is a redirect');

        if (null !== $location) {
            $this->test->assertEquals($location, $this->response->headers->get('Location'), sprintf('Page redirected to "%s"', $location));
        }
    }

    /**
     * Asserts that the response status code is empty.
     */
    public function assertStatusCodeEmpty()
    {
        $this->test->assertTrue(in_array($this->response->getStatusCode(), array(201, 204, 304)), 'Status code is empty');
    }
}
