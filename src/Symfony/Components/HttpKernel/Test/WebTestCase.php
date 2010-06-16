<?php

namespace Symfony\Components\HttpKernel\Test;

use Symfony\Components\DomCrawler\Crawler;
use Symfony\Components\HttpKernel\Client;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * WebTestCase is the base class for functional tests.
 *
 * @package    Symfony
 * @subpackage Components_HttpKernel
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class WebTestCase extends \PHPUnit_Framework_TestCase
{
    protected $currentClient;
    protected $cache;

    /**
     * Creates a Client.
     *
     * This method must set the current client by calling the setCurrentClient() method.
     *
     * @param array   $options An array of options to pass to the createKernel class
     * @param Boolean $debug   The debug flag
     * @param array   $server  An array of server parameters
     *
     * @return Symfony\Foundation\Client A Client instance
     */
    abstract public function createClient(array $options = array(), array $server = array());

    /**
     * Asserts that the response matches a given CSS selector.
     *
     * @param string $selector  A CSS selector
     * @param array  $arguments An array of attributes to extract
     * @param array  $expected  The expected values for the attributes
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseSelectEquals($selector, $arguments, $expected, Client $client = null)
    {
        $this->assertEquals($expected, $this->getCrawler($client)->filter($selector)->extract($arguments));
    }

    /**
     * Asserts that the response matches a given CSS selector n times.
     *
     * @param string  $selector A CSS selector
     * @param integer $count   The number of times the CSS selector must match
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseSelectCount($selector, $count, Client $client = null)
    {
        $this->assertEquals($count, $this->getCrawler($client)->filter($selector)->count(), sprintf('response selector "%s" matches "%s" times', $selector, $count));
    }

    /**
     * Asserts that the response matches a given CSS selector.
     *
     * @param string $selector The CSS selector
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseSelectExists($selector, Client $client = null)
    {
        $this->assertTrue($this->getCrawler($client)->filter($selector)->count() > 0, sprintf('response selector "%s" exists', $selector));
    }

    /**
     * Asserts that the response does not match a given CSS selector.
     *
     * @param string $selector The CSS selector
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseNotSelectExists($selector, Client $client = null)
    {
        $this->assertTrue($this->getCrawler($client)->filter($selector)->count() == 0, sprintf('Response selector "%s" does not exist', $selector));
    }

    /**
     * Asserts the a response header has the given value.
     *
     * @param string $key   The header name
     * @param string $value The header value
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseHeaderEquals($key, $value, Client $client = null)
    {
        $headers = explode(', ', $this->getResponse($client)->headers->get($key));
        foreach ($headers as $header) {
            if ($header == $value) {
                return $this->pass(sprintf('Response header "%s" is "%s" (%s)', $key, $value, $this->getResponse($client)->headers->get($key)));
            }
        }

        $this->fail(sprintf('Response header "%s" matches "%s" (%s)', $key, $value, $this->getResponse($client)->headers->get($key)));
    }

    /**
     * Asserts the a response header has not the given value.
     *
     * @param string $key   The header name
     * @param string $value The header value
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseNotHeaderEquals($key, $value, Client $client = null)
    {
        $headers = explode(', ', $this->getResponse($client)->headers->get($key));
        foreach ($headers as $header) {
            if ($header == $value) {
                return $this->fail(sprintf('Response header "%s" is not "%s" (%s)', $key, $value, $this->getResponse($client)->headers->get($key)));
            }
        }

        $this->pass(sprintf('Response header "%s" does not match "%s" (%s)', $key, $value, $this->getResponse($client)->headers->get($key)));
    }

    /**
     * Asserts the response header matches the given regexp.
     *
     * @param string $key   The header name
     * @param string $regex A regexp
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseHeaderRegExp($key, $regex, Client $client = null)
    {
        $headers = explode(', ', $this->getResponse($client)->headers->get($key));
        foreach ($headers as $header) {
            if (preg_match($regex, $header)) {
                return $this->pass(sprintf('Response header "%s" matches "%s" (%s)', $key, $value, $this->getResponse($client)->headers->get($key)));
            }
        }

        return $this->fail(sprintf('Response header "%s" matches "%s" (%s)', $key, $value, $this->getResponse($client)->headers->get($key)));
    }

    /**
     * Asserts the response header does not match the given regexp.
     *
     * @param string $key   The header name
     * @param string $regex A regexp
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseNotHeaderRegExp($key, $regex, Client $client = null)
    {
        $headers = explode(', ', $this->getResponse($client)->headers->get($key));
        foreach ($headers as $header) {
            if (!preg_match($regex, $header)) {
                return $this->pass(sprintf('Response header "%s" matches "%s" (%s)', $key, $value, $this->getResponse($client)->headers->get($key)));
            }
        }

        return $this->fail(sprintf('Response header "%s" matches "%s" (%s)', $key, $value, $this->getResponse($client)->headers->get($key)));
    }

    /**
     * Asserts if a cookie was set with the given value and attributes.
     *
     * @param  string $name       The cookie name
     * @param  string $value      The cookie value
     * @param  array  $attributes Other cookie attributes to check (expires, path, domain, etc)
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseCookie($name, $value = null, $attributes = array(), Client $client = null)
    {
        foreach ($this->getResponse($client)->getCookies() as $cookie) {
            if ($name == $cookie['name']) {
                if (null === $value) {
                    $this->pass(sprintf('Response sets cookie "%s"', $name));
                } else {
                    $this->assertTrue($value == $cookie['value'], sprintf('Response sets cookie "%s" to "%s"', $name, $value));
                }

                foreach ($attributes as $attributeName => $attributeValue) {
                    if (!array_key_exists($attributeName, $cookie)) {
                        throw new \LogicException(sprintf('The cookie attribute "%s" is not valid.', $attributeName));
                    }

                    $this->assertEquals($attributeValue, $cookie[$attributeName], sprintf('Attribute "%s" of cookie "%s" is "%s"', $attributeName, $name, $attributeValue));
                }

                return;
            }
        }

        $this->fail(sprintf('response sets cookie "%s"', $name));
    }

    /**
     * Asserts that the response content matches a regexp.
     *
     * @param string The regexp
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseRegExp($regex, Client $client = null)
    {
        $this->assertRegExp($regex, $this->getResponse($client)->getContent(), sprintf('Response content matches regex "%s"', $regex));
    }

    /**
     * Asserts that the response content does not match a regexp.
     *
     * @param string The regexp
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseNotRegExp($regex, Client $client = null)
    {
        $this->assertNotRegExp($regex, $this->getResponse($client)->getContent(), sprintf('Response content does not match regex "%s"', substr($regex, 1)));
    }

    /**
     * Asserts the response status code.
     *
     * @param string $statusCode Status code to check
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCode($statusCode, Client $client = null)
    {
        $this->assertEquals($statusCode, $this->getResponse($client)->getStatusCode(), sprintf('Status code is "%s"', $statusCode));
    }

    /**
     * Asserts that the response status code is informational.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCodeInformational(Client $client = null)
    {
        $this->assertTrue($this->getResponse($client)->getStatusCode() >= 100 && $this->getResponse($client)->getStatusCode() < 200, 'Status code is informational');
    }

    /**
     * Asserts that the response status code is successful.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCodeSuccessful(Client $client = null)
    {
        $this->assertTrue($this->getResponse($client)->getStatusCode() >= 200 && $this->getResponse($client)->getStatusCode() < 300, 'Status code is successful');
    }

    /**
     * Asserts that the response status code is a redirection.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCodeRedirection(Client $client = null)
    {
        $this->assertTrue($this->getResponse($client)->getStatusCode() >= 300 && $this->getResponse($client)->getStatusCode() < 400, 'Status code is successful');
    }

    /**
     * Asserts that the response status code is a client error.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCodeClientError(Client $client = null)
    {
        $this->assertTrue($this->getResponse($client)->getStatusCode() >= 400 && $this->getResponse($client)->getStatusCode() < 500, 'Status code is a client error');
    }

    /**
     * Asserts that the response status code is a server error.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCodeServerError(Client $client = null)
    {
        $this->assertTrue($this->getResponse($client)->getStatusCode() >= 500 && $this->getResponse($client)->getStatusCode() < 600, 'Status code is a server error');
    }

    /**
     * Asserts that the response status code is ok.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCodeOk(Client $client = null)
    {
        $this->assertEquals(200, $this->getResponse($client)->getStatusCode(), 'Status code is ok');
    }

    /**
     * Asserts that the response status code is forbidden.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCodeForbidden(Client $client = null)
    {
        $this->assertEquals(403, $this->getResponse($client)->getStatusCode(), 'Status code is forbidden');
    }

    /**
     * Asserts that the response status code is not found.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCodeNotFound(Client $client = null)
    {
        $this->assertEquals(404, $this->getResponse($client)->getStatusCode(), 'Status code is not found');
    }

    /**
     * Asserts that the response status code is a redirect.
     *
     * @param string $location The redirection location
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCodeRedirect($location = null, Client $client = null)
    {
        $this->assertTrue(in_array($this->getResponse($client)->getStatusCode(), array(301, 302, 303, 307)), 'Status code is a redirect');

        if (null !== $location) {
            $this->assertEquals($location, $this->getResponse($client)->headers->get('Location'), sprintf('Page redirected to "%s"', $location));
        }
    }

    /**
     * Asserts that the response status code is empty.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     */
    public function assertResponseStatusCodeEmpty(Client $client = null)
    {
        $this->assertTrue(in_array($this->getResponse($client)->getStatusCode(), array(201, 204, 304)), 'Status code is empty');
    }

    /**
     * Gets the current response associated with the client.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     *
     * @return Symfony\Components\HttpKernel\Response A Response instance
     */
    protected function getResponse(Client $client = null)
    {
        if (null === $client) {
            $client = $this->currentClient;
        }

        return $client->getResponse();
    }

    /**
     * Gets the crawler associated with the client.
     *
     * @param Symfony\Foundation\Client $client A Client instance
     *
     * @return Symfony\Components\DomCrawler\Crawler A Crawler instance
     */
    protected function getCrawler(Client $client = null)
    {
        if (!class_exists('Symfony\Components\DomCrawler\Crawler')) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException(sprintf('Unable to use %s() as the Symfony DomCrawler does not seem to be installed.', __METHOD__));
            // @codeCoverageIgnoreEnd
        }

        if (null === $client) {
            $client = $this->currentClient;
        }

        if (null === $this->cache) {
            $this->cache = new \SplObjectStorage();
        }

        $response = $client->getResponse();
        if (isset($this->cache[$response])) {
            return $this->cache[$response];
        }

        $crawler = new Crawler();
        $crawler->addContent($response->getContent(), $response->headers->get('Content-Type'));

        $this->cache[$response] = $crawler;

        return $crawler;
    }

    protected function setCurrentClient(Client $client)
    {
        $this->currentClient = $client;
    }
}
