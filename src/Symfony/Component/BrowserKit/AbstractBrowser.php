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

use Symfony\Component\BrowserKit\Exception\BadMethodCallException;
use Symfony\Component\BrowserKit\Exception\InvalidArgumentException;
use Symfony\Component\BrowserKit\Exception\LogicException;
use Symfony\Component\BrowserKit\Exception\RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\Process\PhpProcess;

/**
 * Simulates a browser.
 *
 * To make the actual request, you need to implement the doRequest() method.
 *
 * If you want to be able to run requests in their own process (insulated flag),
 * you need to also implement the getScript() method.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractBrowser
{
    protected History $history;
    protected CookieJar $cookieJar;
    protected array $server = [];
    protected Request $internalRequest;
    protected object $request;
    protected Response $internalResponse;
    protected object $response;
    protected Crawler $crawler;
    protected bool $useHtml5Parser = true;
    protected bool $insulated = false;
    protected ?string $redirect;
    protected bool $followRedirects = true;
    protected bool $followMetaRefresh = false;

    private int $maxRedirects = -1;
    private int $redirectCount = 0;
    private array $redirects = [];
    private bool $isMainRequest = true;

    /**
     * @param array $server The server parameters (equivalent of $_SERVER)
     */
    public function __construct(array $server = [], ?History $history = null, ?CookieJar $cookieJar = null)
    {
        $this->setServerParameters($server);
        $this->history = $history ?? new History();
        $this->cookieJar = $cookieJar ?? new CookieJar();
    }

    /**
     * Sets whether to automatically follow redirects or not.
     */
    public function followRedirects(bool $followRedirects = true): void
    {
        $this->followRedirects = $followRedirects;
    }

    /**
     * Sets whether to automatically follow meta refresh redirects or not.
     */
    public function followMetaRefresh(bool $followMetaRefresh = true): void
    {
        $this->followMetaRefresh = $followMetaRefresh;
    }

    /**
     * Returns whether client automatically follows redirects or not.
     */
    public function isFollowingRedirects(): bool
    {
        return $this->followRedirects;
    }

    /**
     * Sets the maximum number of redirects that crawler can follow.
     */
    public function setMaxRedirects(int $maxRedirects): void
    {
        $this->maxRedirects = $maxRedirects < 0 ? -1 : $maxRedirects;
        $this->followRedirects = -1 !== $this->maxRedirects;
    }

    /**
     * Returns the maximum number of redirects that crawler can follow.
     */
    public function getMaxRedirects(): int
    {
        return $this->maxRedirects;
    }

    /**
     * Sets the insulated flag.
     *
     * @throws LogicException When Symfony Process Component is not installed
     */
    public function insulate(bool $insulated = true): void
    {
        if ($insulated && !class_exists(\Symfony\Component\Process\Process::class)) {
            throw new LogicException('Unable to isolate requests as the Symfony Process Component is not installed. Try running "composer require symfony/process".');
        }

        $this->insulated = $insulated;
    }

    /**
     * Sets server parameters.
     */
    public function setServerParameters(array $server): void
    {
        $this->server = array_merge([
            'HTTP_USER_AGENT' => 'Symfony BrowserKit',
        ], $server);
    }

    /**
     * Sets single server parameter.
     */
    public function setServerParameter(string $key, string $value): void
    {
        $this->server[$key] = $value;
    }

    /**
     * Gets single server parameter for specified key.
     */
    public function getServerParameter(string $key, mixed $default = ''): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function xmlHttpRequest(string $method, string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null, bool $changeHistory = true): Crawler
    {
        $this->setServerParameter('HTTP_X_REQUESTED_WITH', 'XMLHttpRequest');

        try {
            return $this->request($method, $uri, $parameters, $files, $server, $content, $changeHistory);
        } finally {
            unset($this->server['HTTP_X_REQUESTED_WITH']);
        }
    }

    /**
     * Converts the request parameters into a JSON string and uses it as request content.
     */
    public function jsonRequest(string $method, string $uri, array $parameters = [], array $server = [], bool $changeHistory = true): Crawler
    {
        $content = json_encode($parameters);

        $this->setServerParameter('CONTENT_TYPE', 'application/json');
        $this->setServerParameter('HTTP_ACCEPT', 'application/json');

        try {
            return $this->request($method, $uri, [], [], $server, $content, $changeHistory);
        } finally {
            unset($this->server['CONTENT_TYPE']);
            unset($this->server['HTTP_ACCEPT']);
        }
    }

    /**
     * Returns the History instance.
     */
    public function getHistory(): History
    {
        return $this->history;
    }

    /**
     * Returns the CookieJar instance.
     */
    public function getCookieJar(): CookieJar
    {
        return $this->cookieJar;
    }

    /**
     * Returns the current Crawler instance.
     */
    public function getCrawler(): Crawler
    {
        return $this->crawler ?? throw new BadMethodCallException(sprintf('The "request()" method must be called before "%s()".', __METHOD__));
    }

    /**
     * Sets whether parsing should be done using "masterminds/html5".
     *
     * @return $this
     */
    public function useHtml5Parser(bool $useHtml5Parser): static
    {
        $this->useHtml5Parser = $useHtml5Parser;

        return $this;
    }

    /**
     * Returns the current BrowserKit Response instance.
     */
    public function getInternalResponse(): Response
    {
        return $this->internalResponse ?? throw new BadMethodCallException(sprintf('The "request()" method must be called before "%s()".', __METHOD__));
    }

    /**
     * Returns the current origin response instance.
     *
     * The origin response is the response instance that is returned
     * by the code that handles requests.
     *
     * @see doRequest()
     */
    public function getResponse(): object
    {
        return $this->response ?? throw new BadMethodCallException(sprintf('The "request()" method must be called before "%s()".', __METHOD__));
    }

    /**
     * Returns the current BrowserKit Request instance.
     */
    public function getInternalRequest(): Request
    {
        return $this->internalRequest ?? throw new BadMethodCallException(sprintf('The "request()" method must be called before "%s()".', __METHOD__));
    }

    /**
     * Returns the current origin Request instance.
     *
     * The origin request is the request instance that is sent
     * to the code that handles requests.
     *
     * @see doRequest()
     */
    public function getRequest(): object
    {
        return $this->request ?? throw new BadMethodCallException(sprintf('The "request()" method must be called before "%s()".', __METHOD__));
    }

    /**
     * Clicks on a given link.
     *
     * @param array $serverParameters An array of server parameters
     */
    public function click(Link $link, array $serverParameters = []): Crawler
    {
        if ($link instanceof Form) {
            return $this->submit($link, [], $serverParameters);
        }

        return $this->request($link->getMethod(), $link->getUri(), [], [], $serverParameters);
    }

    /**
     * Clicks the first link (or clickable image) that contains the given text.
     *
     * @param string $linkText         The text of the link or the alt attribute of the clickable image
     * @param array  $serverParameters An array of server parameters
     */
    public function clickLink(string $linkText, array $serverParameters = []): Crawler
    {
        $crawler = $this->crawler ?? throw new BadMethodCallException(sprintf('The "request()" method must be called before "%s()".', __METHOD__));

        return $this->click($crawler->selectLink($linkText)->link(), $serverParameters);
    }

    /**
     * Submits a form.
     *
     * @param array $values           An array of form field values
     * @param array $serverParameters An array of server parameters
     */
    public function submit(Form $form, array $values = [], array $serverParameters = []): Crawler
    {
        $form->setValues($values);

        return $this->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles(), $serverParameters);
    }

    /**
     * Finds the first form that contains a button with the given content and
     * uses it to submit the given form field values.
     *
     * @param string $button           The text content, id, value or name of the form <button> or <input type="submit">
     * @param array  $fieldValues      Use this syntax: ['my_form[name]' => '...', 'my_form[email]' => '...']
     * @param string $method           The HTTP method used to submit the form
     * @param array  $serverParameters These values override the ones stored in $_SERVER (HTTP headers must include an HTTP_ prefix as PHP does)
     */
    public function submitForm(string $button, array $fieldValues = [], string $method = 'POST', array $serverParameters = []): Crawler
    {
        $crawler = $this->crawler ?? throw new BadMethodCallException(sprintf('The "request()" method must be called before "%s()".', __METHOD__));
        $buttonNode = $crawler->selectButton($button);

        if (0 === $buttonNode->count()) {
            throw new InvalidArgumentException(sprintf('There is no button with "%s" as its content, id, value or name.', $button));
        }

        $form = $buttonNode->form($fieldValues, $method);

        return $this->submit($form, [], $serverParameters);
    }

    /**
     * Calls a URI.
     *
     * @param string $method        The request method
     * @param string $uri           The URI to fetch
     * @param array  $parameters    The Request parameters
     * @param array  $files         The files
     * @param array  $server        The server parameters (HTTP headers are referenced with an HTTP_ prefix as PHP does)
     * @param string $content       The raw body data
     * @param bool   $changeHistory Whether to update the history or not (only used internally for back(), forward(), and reload())
     */
    public function request(string $method, string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null, bool $changeHistory = true): Crawler
    {
        if ($this->isMainRequest) {
            $this->redirectCount = 0;
        } else {
            ++$this->redirectCount;
        }

        $originalUri = $uri;

        $uri = $this->getAbsoluteUri($uri);

        $server = array_merge($this->server, $server);

        if (!empty($server['HTTP_HOST']) && null === parse_url($originalUri, \PHP_URL_HOST)) {
            $uri = preg_replace('{^(https?\://)'.preg_quote($this->extractHost($uri)).'}', '${1}'.$server['HTTP_HOST'], $uri);
        }

        if (isset($server['HTTPS']) && null === parse_url($originalUri, \PHP_URL_SCHEME)) {
            $uri = preg_replace('{^'.parse_url($uri, \PHP_URL_SCHEME).'}', $server['HTTPS'] ? 'https' : 'http', $uri);
        }

        if (!isset($server['HTTP_REFERER']) && !$this->history->isEmpty()) {
            $server['HTTP_REFERER'] = $this->history->current()->getUri();
        }

        if (empty($server['HTTP_HOST'])) {
            $server['HTTP_HOST'] = $this->extractHost($uri);
        }

        $server['HTTPS'] = 'https' === parse_url($uri, \PHP_URL_SCHEME);

        $this->internalRequest = new Request($uri, $method, $parameters, $files, $this->cookieJar->allValues($uri), $server, $content);

        $this->request = $this->filterRequest($this->internalRequest);

        if (true === $changeHistory) {
            $this->history->add($this->internalRequest);
        }

        if ($this->insulated) {
            $this->response = $this->doRequestInProcess($this->request);
        } else {
            $this->response = $this->doRequest($this->request);
        }

        $this->internalResponse = $this->filterResponse($this->response);

        $this->cookieJar->updateFromResponse($this->internalResponse, $uri);

        $status = $this->internalResponse->getStatusCode();

        if ($status >= 300 && $status < 400) {
            $this->redirect = $this->internalResponse->getHeader('Location');
        } else {
            $this->redirect = null;
        }

        if ($this->followRedirects && $this->redirect) {
            $this->redirects[serialize($this->history->current())] = true;

            return $this->crawler = $this->followRedirect();
        }

        $this->crawler = $this->createCrawlerFromContent($this->internalRequest->getUri(), $this->internalResponse->getContent(), $this->internalResponse->getHeader('Content-Type') ?? '');

        // Check for meta refresh redirect
        if ($this->followMetaRefresh && null !== $redirect = $this->getMetaRefreshUrl()) {
            $this->redirect = $redirect;
            $this->redirects[serialize($this->history->current())] = true;
            $this->crawler = $this->followRedirect();
        }

        return $this->crawler;
    }

    /**
     * Makes a request in another process.
     *
     * @return object
     *
     * @throws \RuntimeException When processing returns exit code
     */
    protected function doRequestInProcess(object $request)
    {
        $deprecationsFile = tempnam(sys_get_temp_dir(), 'deprec');
        putenv('SYMFONY_DEPRECATIONS_SERIALIZE='.$deprecationsFile);
        $_ENV['SYMFONY_DEPRECATIONS_SERIALIZE'] = $deprecationsFile;
        $process = new PhpProcess($this->getScript($request), null, null);
        $process->run();

        if (file_exists($deprecationsFile)) {
            $deprecations = file_get_contents($deprecationsFile);
            unlink($deprecationsFile);
            foreach ($deprecations ? unserialize($deprecations) : [] as $deprecation) {
                if ($deprecation[0]) {
                    // unsilenced on purpose
                    trigger_error($deprecation[1], \E_USER_DEPRECATED);
                } else {
                    @trigger_error($deprecation[1], \E_USER_DEPRECATED);
                }
            }
        }

        if (!$process->isSuccessful() || !preg_match('/^O\:\d+\:/', $process->getOutput())) {
            throw new RuntimeException(sprintf('OUTPUT: %s ERROR OUTPUT: %s.', $process->getOutput(), $process->getErrorOutput()));
        }

        return unserialize($process->getOutput());
    }

    /**
     * Makes a request.
     *
     * @return object
     */
    abstract protected function doRequest(object $request);

    /**
     * Returns the script to execute when the request must be insulated.
     *
     * @param object $request An origin request instance
     *
     * @return string
     *
     * @throws LogicException When this abstract class is not implemented
     */
    protected function getScript(object $request)
    {
        throw new LogicException('To insulate requests, you need to override the getScript() method.');
    }

    /**
     * Filters the BrowserKit request to the origin one.
     *
     * @return object
     */
    protected function filterRequest(Request $request)
    {
        return $request;
    }

    /**
     * Filters the origin response to the BrowserKit one.
     *
     * @return Response
     */
    protected function filterResponse(object $response)
    {
        return $response;
    }

    /**
     * Creates a crawler.
     *
     * This method returns null if the DomCrawler component is not available.
     */
    protected function createCrawlerFromContent(string $uri, string $content, string $type): ?Crawler
    {
        if (!class_exists(Crawler::class)) {
            return null;
        }

        $crawler = new Crawler(null, $uri, null, $this->useHtml5Parser);
        $crawler->addContent($content, $type);

        return $crawler;
    }

    /**
     * Goes back in the browser history.
     */
    public function back(): Crawler
    {
        do {
            $request = $this->history->back();
        } while (\array_key_exists(serialize($request), $this->redirects));

        return $this->requestFromRequest($request, false);
    }

    /**
     * Goes forward in the browser history.
     */
    public function forward(): Crawler
    {
        do {
            $request = $this->history->forward();
        } while (\array_key_exists(serialize($request), $this->redirects));

        return $this->requestFromRequest($request, false);
    }

    /**
     * Reloads the current browser.
     */
    public function reload(): Crawler
    {
        return $this->requestFromRequest($this->history->current(), false);
    }

    /**
     * Follow redirects?
     *
     * @throws LogicException If request was not a redirect
     */
    public function followRedirect(): Crawler
    {
        if (!$this->redirect) {
            throw new LogicException('The request was not redirected.');
        }

        if (-1 !== $this->maxRedirects) {
            if ($this->redirectCount > $this->maxRedirects) {
                $this->redirectCount = 0;
                throw new LogicException(sprintf('The maximum number (%d) of redirections was reached.', $this->maxRedirects));
            }
        }

        $request = $this->internalRequest;

        if (\in_array($this->internalResponse->getStatusCode(), [301, 302, 303])) {
            $method = 'GET';
            $files = [];
            $content = null;
        } else {
            $method = $request->getMethod();
            $files = $request->getFiles();
            $content = $request->getContent();
        }

        if ('GET' === strtoupper($method)) {
            // Don't forward parameters for GET request as it should reach the redirection URI
            $parameters = [];
        } else {
            $parameters = $request->getParameters();
        }

        $server = $request->getServer();
        $server = $this->updateServerFromUri($server, $this->redirect);

        $this->isMainRequest = false;

        $response = $this->request($method, $this->redirect, $parameters, $files, $server, $content);

        $this->isMainRequest = true;

        return $response;
    }

    /**
     * @see https://dev.w3.org/html5/spec-preview/the-meta-element.html#attr-meta-http-equiv-refresh
     */
    private function getMetaRefreshUrl(): ?string
    {
        $metaRefresh = $this->getCrawler()->filter('head meta[http-equiv="refresh"]');
        foreach ($metaRefresh->extract(['content']) as $content) {
            if (preg_match('/^\s*0\s*;\s*URL\s*=\s*(?|\'([^\']++)|"([^"]++)|([^\'"].*))/i', $content, $m)) {
                return str_replace("\t\r\n", '', rtrim($m[1]));
            }
        }

        return null;
    }

    /**
     * Restarts the client.
     *
     * It flushes history and all cookies.
     */
    public function restart(): void
    {
        $this->cookieJar->clear();
        $this->history->clear();
    }

    /**
     * Takes a URI and converts it to absolute if it is not already absolute.
     */
    protected function getAbsoluteUri(string $uri): string
    {
        // already absolute?
        if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
            return $uri;
        }

        if (!$this->history->isEmpty()) {
            $currentUri = $this->history->current()->getUri();
        } else {
            $currentUri = sprintf('http%s://%s/',
                isset($this->server['HTTPS']) ? 's' : '',
                $this->server['HTTP_HOST'] ?? 'localhost'
            );
        }

        // protocol relative URL
        if ('' !== trim($uri, '/') && str_starts_with($uri, '//')) {
            return parse_url($currentUri, \PHP_URL_SCHEME).':'.$uri;
        }

        // anchor or query string parameters?
        if (!$uri || '#' === $uri[0] || '?' === $uri[0]) {
            return preg_replace('/[#?].*?$/', '', $currentUri).$uri;
        }

        if ('/' !== $uri[0]) {
            $path = parse_url($currentUri, \PHP_URL_PATH);

            if (!str_ends_with($path, '/')) {
                $path = substr($path, 0, strrpos($path, '/') + 1);
            }

            $uri = $path.$uri;
        }

        return preg_replace('#^(.*?//[^/]+)\/.*$#', '$1', $currentUri).$uri;
    }

    /**
     * Makes a request from a Request object directly.
     *
     * @param bool $changeHistory Whether to update the history or not (only used internally for back(), forward(), and reload())
     */
    protected function requestFromRequest(Request $request, bool $changeHistory = true): Crawler
    {
        return $this->request($request->getMethod(), $request->getUri(), $request->getParameters(), $request->getFiles(), $request->getServer(), $request->getContent(), $changeHistory);
    }

    private function updateServerFromUri(array $server, string $uri): array
    {
        $server['HTTP_HOST'] = $this->extractHost($uri);
        $scheme = parse_url($uri, \PHP_URL_SCHEME);
        $server['HTTPS'] = null === $scheme ? $server['HTTPS'] : 'https' === $scheme;
        unset($server['HTTP_IF_NONE_MATCH'], $server['HTTP_IF_MODIFIED_SINCE']);

        return $server;
    }

    private function extractHost(string $uri): ?string
    {
        $host = parse_url($uri, \PHP_URL_HOST);

        if ($port = parse_url($uri, \PHP_URL_PORT)) {
            return $host.':'.$port;
        }

        return $host;
    }
}
