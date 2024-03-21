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
 * Any HTML element that can link to an URI.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractUriElement
{
    /**
     * @deprecated since Symfony 7.1, use `$domeNode` instead
     */
    protected \DOMElement $node;
    protected \DOMElement|\DOM\Element $domNode;
    protected ?string $method;

    /**
     * @param \DOMElement|\DOM\Element $node       A \DOMElement or a \DOM\Element instance
     * @param string|null              $currentUri The URI of the page where the link is embedded (or the base href)
     * @param string|null              $method     The method to use for the link (GET by default)
     *
     * @throws \InvalidArgumentException if the node is not a link
     */
    public function __construct(
        \DOMElement|\DOM\Element $node,
        protected ?string $currentUri = null,
        ?string $method = 'GET',
    ) {
        $this->setDomNode($node);
        $this->method = $method ? strtoupper($method) : null;

        $elementUriIsRelative = null === parse_url(trim($this->getRawUri()), \PHP_URL_SCHEME);
        $baseUriIsAbsolute = null !== $this->currentUri && \in_array(strtolower(substr($this->currentUri, 0, 4)), ['http', 'file']);
        if ($elementUriIsRelative && !$baseUriIsAbsolute) {
            throw new \InvalidArgumentException(\sprintf('The URL of the element is relative, so you must define its base URI passing an absolute URL to the constructor of the "%s" class ("%s" was passed).', __CLASS__, $this->currentUri));
        }
    }

    /**
     * @deprecated since Symfony 7.1, use `getDomNode()` instead
     */
    public function getNode(): \DOMElement
    {
        trigger_deprecation('symfony/dom-crawler', '7.1', 'The "%s()" method is deprecated, use "%s::getDomNode()" instead.', __METHOD__, __CLASS__);

        if ($this->domNode instanceof \DOM\Element) {
            throw new \LogicException('The node is not an instance of legacy \DOMElement. Use "getDomNode()" instead.');
        }

        return $this->domNode;
    }

    /**
     * Gets the node associated with this link.
     */
    public function getDomNode(): \DOMElement|\DOM\Element
    {
        return $this->domNode;
    }

    /**
     * Gets the method associated with this link.
     */
    public function getMethod(): string
    {
        return $this->method ?? 'GET';
    }

    /**
     * Gets the URI associated with this link.
     */
    public function getUri(): string
    {
        return UriResolver::resolve($this->getRawUri(), $this->currentUri);
    }

    /**
     * Returns raw URI data.
     */
    abstract protected function getRawUri(): string;

    /**
     * Returns the canonicalized URI path (see RFC 3986, section 5.2.4).
     *
     * @param string $path URI path
     */
    protected function canonicalizePath(string $path): string
    {
        if ('' === $path || '/' === $path) {
            return $path;
        }

        if (str_ends_with($path, '.')) {
            $path .= '/';
        }

        $output = [];

        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                array_pop($output);
            } elseif ('.' !== $segment) {
                $output[] = $segment;
            }
        }

        return implode('/', $output);
    }

    /**
     * Sets current \DOMElement instance.
     *
     * @param \DOMElement $node A \DOMElement instance
     *
     * @throws \LogicException If given node is not an anchor
     */
    abstract protected function setNode(\DOMElement $node): void;

    /**
     * Sets current \DOMElement or \DOM\Element instance.
     *
     * @param \DOMElement|\DOM\Element $node A \DOMElement or \DOM\Element instance
     *
     * @throws \LogicException If given node is not an anchor
     */
    protected function setDomNode(\DOMElement|\DOM\Element $node): void
    {
        $this->domNode = $node;

        if ($node instanceof \DOMElement) {
            $this->setNode($node);
        }
    }
}
