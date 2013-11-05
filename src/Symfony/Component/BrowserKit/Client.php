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

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Process\PhpProcess;

/**
 * Client simulates a browser.
 *
 * To make the actual request, you need to implement the doRequest() method.
 *
 * If you want to be able to run requests in their own process (insulated flag),
 * you need to also implement the getScript() method.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
abstract class Client
{
    protected $history;
    protected $cookieJar;
    protected $server;
    protected $request;
    protected $response;
    protected $crawler;
    protected $insulated;
    protected $redirect;
    protected $followRedirects;

    private $internalRequest;
    private $internalResponse;

    /**
     * Constructor.
     *
     * @param array     $server    The server parameters (equivalent of $_SERVER)
     * @param History   $history   A History instance to store the browser history
     * @param CookieJar $cookieJar A CookieJar instance to store the cookies
     *
     * @api
     */
    public function __construct(array $server = array(), History $history = null, CookieJar $cookieJar = null)
    {
        $this->setServerParameters($server);
        $this->history = null === $history ? new History() : $history;
        $this->cookieJar = null === $cookieJar ? new CookieJar() : $cookieJar;
        $this->insulated = false;
        $this->followRedirects = true;
    }

    /**
     * Sets whether to automatically follow redirects or not.
     *
     * @param Boolean $followRedirect Whether to follow redirects
     *
     * @api
     */
    public function followRedirects($followRedirect = true)
    {
        $this->followRedirects = (Boolean) $followRedirect;
    }

    /**
     * Sets the insulated flag.
     *
     * @param Boolean $insulated Whether to insulate the requests or not
     *
     * @throws \RuntimeException When Symfony Process Component is not installed
     *
     * @api
     */
    public function insulate($insulated = true)
    {
        if ($insulated && !class_exists('Symfony\\Component\\Process\\Process')) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('Unable to isolate requests as the Symfony Process Component is not installed.');
            // @codeCoverageIgnoreEnd
        }

        $this->insulated = (Boolean) $insulated;
    }

    /**
     * Sets server parameters.
     *
     * @param array $server An array of server parameters
     *
     * @api
     */
    public function setServerParameters(array $server)
    {
        $this->server = array_merge(array(
            'HTTP_HOST'       => 'localhost',
            'HTTP_USER_AGENT' => 'Symfony2 BrowserKit',
        ), $server);
    }

    /**
     * Sets single server parameter.
     *
     * @param string $key   A key of the parameter
     * @param string $value A value of the parameter
     */
    public function setServerParameter($key, $value)
    {
        $this->server[$key] = $value;
    }

    /**
     * Gets single server parameter for specified key.
     *
     * @param string $key     A key of the parameter to get
     * @param string $default A default value when key is undefined
     *
     * @return string A value of the parameter
     */
    public function getServerParameter($key, $default = '')
    {
        return (isset($this->server[$key])) ? $this->server[$key] : $default;
    }

    /**
     * Returns the History instance.
     *
     * @return History A History instance
     *
     * @api
     */
    public function getHistory()
    {
        return $this->history;
    }

    /**
     * Returns the CookieJar instance.
     *
     * @return CookieJar A CookieJar instance
     *
     * @api
     */
    public function getCookieJar()
    {
        return $this->cookieJar;
    }

    /**
     * Returns the current Crawler instance.
     *
     * @return Crawler A Crawler instance
     *
     * @api
     */
    public function getCrawler()
    {
        return $this->crawler;
    }

    /**
     * Returns the current Response instance.
     *
     * @return Response A Response instance
     *
     * @api
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Returns the current Request instance.
     *
     * @return Request A Request instance
     *
     * @api
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Clicks on a given link.
     *
     * @param Link $link A Link instance
     *
     * @return Crawler
     *
     * @api
     */
    public function click(Link $link)
    {
        if ($link instanceof Form) {
            return $this->submit($link);
        }

        return $this->request($link->getMethod(), $link->getUri());
    }

    /**
     * Submits a form.
     *
     * @param Form  $form   A Form instance
     * @param array $values An array of form field values
     *
     * @return Crawler
     *
     * @api
     */
    public function submit(Form $form, array $values = array())
    {
        $form->setValues($values);

        return $this->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
    }

    /**
     * Calls a URI.
     *
     * @param string  $method        The request method
     * @param string  $uri           The URI to fetch
     * @param array   $parameters    The Request parameters
     * @param array   $files         The files
     * @param array   $server        The server parameters (HTTP headers are referenced with a HTTP_ prefix as PHP does)
     * @param string  $content       The raw body data
     * @param Boolean $changeHistory Whether to update the history or not (only used internally for back(), forward(), and reload())
     *
     * @return Crawler
     *
     * @api
     */
    public function request($method, $uri, array $parameters = array(), array $files = array(), array $server = array(), $content = null, $changeHistory = true)
    {
        $uri = $this->getAbsoluteUri($uri);

        $server = array_merge($this->server, $server);
        if (!$this->history->isEmpty()) {
            $server['HTTP_REFERER'] = $this->history->current()->getUri();
        }
        $server['HTTP_HOST'] = parse_url($uri, PHP_URL_HOST);
        $server['HTTPS'] = 'https' == parse_url($uri, PHP_URL_SCHEME);

        $this->internalRequest = $request = new Request($uri, $method, $parameters, $files, $this->cookieJar->allValues($uri), $server, $content);

        $this->request = $this->filterRequest($request);

        if (true === $changeHistory) {
            $this->history->add($request);
        }

        if ($this->insulated) {
            $this->response = $this->doRequestInProcess($this->request);
        } else {
            $this->response = $this->doRequest($this->request);
        }

        $this->internalResponse = $response = $this->filterResponse($this->response);

        $this->cookieJar->updateFromResponse($response, $uri);

        $this->redirect = $response->getHeader('Location');

        if ($this->followRedirects && $this->redirect) {
            return $this->crawler = $this->followRedirect();
        }

        return $this->crawler = $this->createCrawlerFromContent($request->getUri(), $response->getContent(), $response->getHeader('Content-Type'));
    }

    /**
     * Makes a request in another process.
     *
     * @param Request $request A Request instance
     *
     * @return Response A Response instance
     *
     * @throws \RuntimeException When processing returns exit code
     */
    protected function doRequestInProcess($request)
    {
        // We set the TMPDIR (for Macs) and TEMP (for Windows), because on these platforms the temp directory changes based on the user.
        $process = new PhpProcess($this->getScript($request), null, array('TMPDIR' => sys_get_temp_dir(), 'TEMP' => sys_get_temp_dir()));
        $process->run();

        if (!$process->isSuccessful() || !preg_match('/^O\:\d+\:/', $process->getOutput())) {
            throw new \RuntimeException('OUTPUT: '.$process->getOutput().' ERROR OUTPUT: '.$process->getErrorOutput());
        }

        return unserialize($process->getOutput());
    }

    /**
     * Makes a request.
     *
     * @param Request $request A Request instance
     *
     * @return Response A Response instance
     */
    abstract protected function doRequest($request);

    /**
     * Returns the script to execute when the request must be insulated.
     *
     * @param Request $request A Request instance
     *
     * @throws \LogicException When this abstract class is not implemented
     */
    protected function getScript($request)
    {
        // @codeCoverageIgnoreStart
        throw new \LogicException('To insulate requests, you need to override the getScript() method.');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Filters the request.
     *
     * @param Request $request The request to filter
     *
     * @return Request
     */
    protected function filterRequest(Request $request)
    {
        return $request;
    }

    /**
     * Filters the Response.
     *
     * @param Response $response The Response to filter
     *
     * @return Response
     */
    protected function filterResponse($response)
    {
        return $response;
    }

    /**
     * Creates a crawler.
     *
     * This method returns null if the DomCrawler component is not available.
     *
     * @param string $uri     A uri
     * @param string $content Content for the crawler to use
     * @param string $type    Content type
     *
     * @return Crawler|null
     */
    protected function createCrawlerFromContent($uri, $content, $type)
    {
        if (!class_exists('Symfony\Component\DomCrawler\Crawler')) {
            return null;
        }

        $crawler = new Crawler(null, $uri);
        $crawler->addContent($content, $type);

        return $crawler;
    }

    /**
     * Goes back in the browser history.
     *
     * @return Crawler
     *
     * @api
     */
    public function back()
    {
        return $this->requestFromRequest($this->history->back(), false);
    }

    /**
     * Goes forward in the browser history.
     *
     * @return Crawler
     *
     * @api
     */
    public function forward()
    {
        return $this->requestFromRequest($this->history->forward(), false);
    }

    /**
     * Reloads the current browser.
     *
     * @return Crawler
     *
     * @api
     */
    public function reload()
    {
        return $this->requestFromRequest($this->history->current(), false);
    }

    /**
     * Follow redirects?
     *
     * @return Crawler
     *
     * @throws \LogicException If request was not a redirect
     *
     * @api
     */
    public function followRedirect()
    {
        if (empty($this->redirect)) {
            throw new \LogicException('The request was not redirected.');
        }

        $request = $this->internalRequest;

        if (in_array($this->internalResponse->getStatus(), array(302, 303))) {
            $method = 'get';
            $files = array();
            $content = null;
        } else {
            $method = $request->getMethod();
            $files = $request->getFiles();
            $content = $request->getContent();
        }

        if ('get' === strtolower($method)) {
            // Don't forward parameters for GET request as it should reach the redirection URI
            $parameters = array();
        } else {
            $parameters = $request->getParameters();
        }

        $server = $request->getServer();
        unset($server['HTTP_IF_NONE_MATCH'], $server['HTTP_IF_MODIFIED_SINCE']);

        return $this->request($method, $this->redirect, $parameters, $files, $server, $content);
    }

    /**
     * Restarts the client.
     *
     * It flushes history and all cookies.
     *
     * @api
     */
    public function restart()
    {
        $this->cookieJar->clear();
        $this->history->clear();
    }

    /**
     * Takes a URI and converts it to absolute if it is not already absolute.
     *
     * @param string $uri A uri
     *
     * @return string An absolute uri
     */
    protected function getAbsoluteUri($uri)
    {
        // already absolute?
        if (0 === strpos($uri, 'http')) {
            return $uri;
        }

        if (!$this->history->isEmpty()) {
            $currentUri = $this->history->current()->getUri();
        } else {
            $currentUri = sprintf('http%s://%s/',
                isset($this->server['HTTPS']) ? 's' : '',
                isset($this->server['HTTP_HOST']) ? $this->server['HTTP_HOST'] : 'localhost'
            );
        }

        // protocol relative URL
        if (0 === strpos($uri, '//')) {
            return parse_url($currentUri, PHP_URL_SCHEME).':'.$uri;
        }

        // anchor?
        if (!$uri || '#' == $uri[0]) {
            return preg_replace('/#.*?$/', '', $currentUri).$uri;
        }

        if ('/' !== $uri[0]) {
            $path = parse_url($currentUri, PHP_URL_PATH);

            if ('/' !== substr($path, -1)) {
                $path = substr($path, 0, strrpos($path, '/') + 1);
            }

            $uri = $path.$uri;
        }

        return preg_replace('#^(.*?//[^/]+)\/.*$#', '$1', $currentUri).$uri;
    }

    /**
     * Makes a request from a Request object directly.
     *
     * @param Request $request       A Request instance
     * @param Boolean $changeHistory Whether to update the history or not (only used internally for back(), forward(), and reload())
     *
     * @return Crawler
     */
    protected function requestFromRequest(Request $request, $changeHistory = true)
    {
        return $this->request($request->getMethod(), $request->getUri(), $request->getParameters(), $request->getFiles(), $request->getServer(), $request->getContent(), $changeHistory);
    }
}
