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
    protected $node;
    protected $method;
    protected $currentUri;

    /**
     * Constructor.
     *
     * @param \DOMNode $node       A \DOMNode instance
     * @param string   $currentUri The URI of the page where the link is embedded (or the base href)
     * @param string   $method     The method to use for the link (get by default)
     *
     * @throws \LogicException if the node is not a link
     *
     * @api
     */
    public function __construct(\DOMNode $node, $currentUri, $method = 'GET')
    {
        if (!in_array(substr($currentUri, 0, 4), array('http', 'file'))) {
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
        if (0 === strpos($uri, 'http')) {
            return $uri;
        }

        // empty URI
        if (!$uri) {
            return $this->currentUri;
        }

        // only an anchor
        if ('#' ===  $uri[0]) {
            $baseUri = $this->currentUri;
            if (false !== $pos = strpos($baseUri, '#')) {
                $baseUri = substr($baseUri, 0, $pos);
            }

            return $baseUri.$uri;
        }

        // only a query string
        if ('?' === $uri[0]) {
            $baseUri = $this->currentUri;

            // remove the query string from the current uri
            if (false !== $pos = strpos($baseUri, '?')) {
                $baseUri = substr($baseUri, 0, $pos);
            }

            return $baseUri.$uri;
        }

        // absolute path
        if ('/' === $uri[0]) {
            return preg_replace('#^(.*?//[^/]+)(?:\/.*)?$#', '$1', $this->currentUri).$uri;
        }

        // relative path
        return substr($this->currentUri, 0, strrpos($this->currentUri, '/') + 1).$uri;
    }

    protected function getRawUri()
    {
        return $this->node->getAttribute('href');
    }

    protected function setNode(\DOMNode $node)
    {
        if ('a' != $node->nodeName) {
            throw new \LogicException(sprintf('Unable to click on a "%s" tag.', $node->nodeName));
        }

        $this->node = $node;
    }
}
