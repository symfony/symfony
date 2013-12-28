<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler;

/**
 * Link represents an HTML link (an HTML a tag).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Link
{
    /**
     * @var \DOMNode A \DOMNode instance
     */
    protected $node;
    /**
     * @var string The method to use for the link
     */
    protected $method;
    /**
     * @var string The URI of the page where the link is embedded (or the base href)
     */
    protected $currentUri;

    /**
     * Constructor.
     *
     * @param \DOMNode $node       A \DOMNode instance
     * @param string   $currentUri The URI of the page where the link is embedded (or the base href)
     * @param string   $method     The method to use for the link (get by default)
     *
     * @throws \InvalidArgumentException if the node is not a link
     *
     * @api
     */
    public function __construct(\DOMNode $node, $currentUri, $method = 'GET')
    {
        if (!in_array(strtolower(substr($currentUri, 0, 4)), array('http', 'file'))) {
            throw new \InvalidArgumentException(sprintf('Current URI must be an absolute URL ("%s").', $currentUri));
        }

        $this->setNode($node);
        $this->method = $method ? strtoupper($method) : null;
        $this->currentUri = $currentUri;
    }

    /**
     * Gets the node associated with this link.
     *
     * @return \DOMNode A \DOMNode instance
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * Gets the method associated with this link.
     *
     * @return string The method
     *
     * @api
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Gets the URI associated with this link.
     *
     * @return string The URI
     *
     * @api
     */
    public function getUri()
    {
        $uri = trim($this->getRawUri());

        // absolute URL?
        if (null !== parse_url($uri, PHP_URL_SCHEME)) {
            return $uri;
        }

        // empty URI
        if (!$uri) {
            return $this->currentUri;
        }

        // only an anchor
        if ('#' === $uri[0]) {
            $baseUri = $this->currentUri;
            if (false !== $pos = strpos($baseUri, '#')) {
                $baseUri = substr($baseUri, 0, $pos);
            }

            return $baseUri.$uri;
        }

        // only a query string
        if ('?' === $uri[0]) {
            $baseUri = $this->currentUri;

            // remove the query string from the current URI
            if (false !== $pos = strpos($baseUri, '?')) {
                $baseUri = substr($baseUri, 0, $pos);
            }

            return $baseUri.$uri;
        }

        // absolute URL with relative schema
        if (0 === strpos($uri, '//')) {
            return preg_replace('#^([^/]*)//.*$#', '$1', $this->currentUri).$uri;
        }

        $baseUri = preg_replace('#^(.*?//[^/]*)(?:\/.*)?$#', '$1', $this->currentUri);

        // absolute path
        if ('/' === $uri[0]) {
            return $baseUri.$uri;
        }

        // relative path
        $path = parse_url(substr($this->currentUri, strlen($baseUri)), PHP_URL_PATH);
        $path = $this->canonicalizePath(substr($path, 0, strrpos($path, '/')).'/'.$uri);

        return $baseUri.('' === $path || '/' !== $path[0] ? '/' : '').$path;
    }

    /**
     * Returns raw URI data.
     *
     * @return string
     */
    protected function getRawUri()
    {
        return $this->node->getAttribute('href');
    }

    /**
     * Returns the canonicalized URI path (see RFC 3986, section 5.2.4)
     *
     * @param string $path URI path
     *
     * @return string
     */
    protected function canonicalizePath($path)
    {
        if ('' === $path || '/' === $path) {
            return $path;
        }

        if ('.' === substr($path, -1)) {
            $path = $path.'/';
        }

        $output = array();

        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                array_pop($output);
            } elseif ('.' !== $segment) {
                array_push($output, $segment);
            }
        }

        return implode('/', $output);
    }

    /**
     * Sets current \DOMNode instance.
     *
     * @param \DOMNode $node A \DOMNode instance
     *
     * @throws \LogicException If given node is not an anchor
     */
    protected function setNode(\DOMNode $node)
    {
        if ('a' != $node->nodeName) {
            throw new \LogicException(sprintf('Unable to click on a "%s" tag.', $node->nodeName));
        }

        $this->node = $node;
    }
}
