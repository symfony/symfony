<?php

namespace Symfony\Component\DomCrawler;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Link represents an HTML link (an HTML a tag).
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Link
{
    protected $node;
    protected $method;
    protected $host;
    protected $path;

    /**
     * Constructor.
     *
     * @param \DOMNode $node   A \DOMNode instance
     * @param string   $method The method to use for the link (get by default)
     * @param string   $host   The base URI to use for absolute links (like http://localhost)
     * @param string   $path   The base path for relative links (/ by default)
     *
     * @throws \LogicException if the node is not a link
     */
    public function __construct(\DOMNode $node, $method = 'get', $host = null, $path = '/')
    {
        if ('a' != $node->nodeName) {
            throw new \LogicException(sprintf('Unable to click on a "%s" tag.', $node->nodeName));
        }

        $this->node = $node;
        $this->method = $method;
        $this->host = $host;
        $this->path = empty($path) ? '/' : $path;
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
     * Gets the URI associated with this link.
     *
     * @param Boolean $absolute Whether to return an absolute URI or not (this only works if a base URI has been provided)
     *
     * @return string The URI
     */
    public function getUri($absolute = true)
    {
        $uri = $this->node->getAttribute('href');
        $urlHaveScheme = 'http' === substr($uri, 0, 4);

        $path = $this->path;
        if ('?' !== substr($uri, 0, 1) && '/' !== substr($path, -1)) {
            $path = substr($path, 0, strrpos($path, '/') + 1);
        }

        if ($uri && '/' !== $uri[0] && !$urlHaveScheme) {
            $uri = $path.$uri;
        }

        if ($absolute && null !== $this->host && !$urlHaveScheme) {
            return $this->host.$uri;
        }

        return $uri;
    }

    /**
     * Gets the method associated with this link.
     *
     * @return string The method
     */
    public function getMethod()
    {
        return $this->method;
    }
}
