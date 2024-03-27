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

use Masterminds\HTML5;

/**
 * DomCrawler eases navigation of a list of \DOMNode or \DOM\Node objects.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @implements \IteratorAggregate<int, \DOMNode>|\IteratorAggregate<int, \DOM\Node>
 */
class DomCrawler extends Crawler
{
    /**
     * Whether to parse HTML5 content or consider everything as XML.
     */
    public const CRAWLER_ENABLE_HTML5_PARSING = 0b1;

    /**
     * Use an external parser to parse the HTML content, or libxml for XML content.
     */
    public const CRAWLER_USE_EXTERNAL_PARSER = 0b10;

    /**
     * Available since PHP 8.4 to use the native DOM extension parser.
     * The native parser provides better performance but strictly follows
     * the XML specification, which may lead to different results compared
     * to the external parser or libxml.
     */
    public const CRAWLER_USE_NATIVE_PARSER = 0b100;

    /**
     * @param \DOMNodeList|\DOM\NodeList|\DOMNode|\DOM\Node|\DOMNode[]|\DOM\Node[]|string|null $node    A Node to use as the base for the crawling
     * @param int                                                                              $options A bitmask of CRAWLER_* constants
     */
    public function __construct(
        \DOMNodeList|\DOM\NodeList|\DOMNode|\DOM\Node|array|string|null $node = null,
        ?string $uri = null,
        ?string $baseHref = null,
        protected int $options = self::CRAWLER_ENABLE_HTML5_PARSING | self::CRAWLER_USE_EXTERNAL_PARSER,
    ) {
        if ($this->options & self::CRAWLER_USE_NATIVE_PARSER && \PHP_VERSION_ID < 80400) {
            throw new \InvalidArgumentException('Native parser requires PHP 8.4 or higher.');
        }

        if ($this->options & self::CRAWLER_USE_EXTERNAL_PARSER && $this->options & self::CRAWLER_USE_NATIVE_PARSER) {
            throw new \InvalidArgumentException('You cannot use both external and native parsers at the same time.');
        }

        if ($this->options & self::CRAWLER_ENABLE_HTML5_PARSING && !($this->options & self::CRAWLER_USE_EXTERNAL_PARSER) && !($this->options & self::CRAWLER_USE_NATIVE_PARSER)) {
            throw new \InvalidArgumentException('You must either choose the external or the native parser when enable HTML 5 parsing.');
        }

        $this->uri = $uri;
        $this->baseHref = $baseHref ?: $uri;

        if ($this->options & self::CRAWLER_ENABLE_HTML5_PARSING && $this->options & self::CRAWLER_USE_EXTERNAL_PARSER) {
            $this->html5Parser = new HTML5(['disable_html_ns' => true]);
        }

        $this->cachedNamespaces = new \ArrayObject();
        $this->add($node);
    }

    protected function createSubCrawler(\DOMNode|\DOMNodeList|\DOM\Node|array|string|\DOM\NodeList|null $nodes): static
    {
        $crawler = parent::createSubCrawler($nodes);
        $crawler->options = $this->options;

        return $crawler;
    }

    /**
     * Adds a node to the current list of nodes.
     *
     * This method uses the appropriate specialized add*() method based
     * on the type of the argument.
     *
     * @param \DOMNodeList|\DOMNode|\DOMNode[]|string|null $node A node
     *
     * @throws \InvalidArgumentException when node is not the expected type
     */
    public function add(\DOMNodeList|\DOM\NodeList|\DOMNode|\DOM\Node|array|string|null $node): void
    {
        if ($node instanceof \DOMNodeList || $node instanceof \DOM\NodeList) {
            $this->addNodeList($node);
        } elseif ($node instanceof \DOMNode || $node instanceof \DOM\Node) {
            $this->addNode($node);
        } elseif (\is_array($node)) {
            $this->addNodes($node);
        } elseif (\is_string($node)) {
            $this->addContent($node);
        } elseif (null !== $node) {
            throw new \InvalidArgumentException(sprintf('Expecting a DOMNodeList or DOMNode instance, an array, a string, or null, but got "%s".', get_debug_type($node)));
        }
    }

    /**
     * Adds an HTML content to the list of nodes.
     *
     * The libxml errors are disabled when the content is parsed.
     *
     * If you want to get parsing errors, be sure to enable
     * internal errors via libxml_use_internal_errors(true)
     * and then, get the errors via libxml_get_errors(). Be
     * sure to clear errors with libxml_clear_errors() afterward.
     */
    public function addHtmlContent(string $content, string $charset = 'UTF-8'): void
    {
        $dom = $this->parseHtmlString($content, $charset);
        $this->addDocument($dom);

        $base = $this->filterRelativeXPath('descendant-or-self::base')->extract(['href']);

        $baseHref = current($base);
        if (\count($base) && !empty($baseHref)) {
            if ($this->baseHref) {
                $linkNode = $dom->createElement('a');
                $linkNode->setAttribute('href', $baseHref);
                $link = new Link($linkNode, $this->baseHref);
                $this->baseHref = $link->getUri();
            } else {
                $this->baseHref = $baseHref;
            }
        }
    }

    /**
     * Adds an XML content to the list of nodes.
     *
     * The libxml errors are disabled when the content is parsed.
     *
     * If you want to get parsing errors, be sure to enable
     * internal errors via libxml_use_internal_errors(true)
     * and then, get the errors via libxml_get_errors(). Be
     * sure to clear errors with libxml_clear_errors() afterward.
     *
     * @param int $options Bitwise OR of the libxml option constants
     *                     LIBXML_PARSEHUGE is dangerous, see
     *                     http://symfony.com/blog/security-release-symfony-2-0-17-released
     */
    public function addXmlContent(string $content, string $charset = 'UTF-8', int $options = \LIBXML_NONET): void
    {
        // remove the default namespace if it's the only namespace to make XPath expressions simpler
        if (!str_contains($content, 'xmlns:')) {
            $content = str_replace('xmlns', 'ns', $content);
        }

        $internalErrors = libxml_use_internal_errors(true);

        if ($this->options & self::CRAWLER_USE_NATIVE_PARSER) {
            try {
                $dom = \DOM\XMLDocument::createFromString($content, $options);
            } catch (\Exception) {
                // like with legacy nodes, create an empty document if
                // content cannot be loaded
                $dom = \DOM\XMLDocument::createEmpty();
            }
        } else {
            $dom = new \DOMDocument('1.0', $charset);
            $dom->validateOnParse = true;

            if ('' !== trim($content)) {
                @$dom->loadXML($content, $options);
            }
        }

        libxml_use_internal_errors($internalErrors);

        $this->addDocument($dom);

        $this->isHtml = false;
    }

    /**
     * Adds a \DOMDocument or a \DOM\Document to the list of nodes.
     */
    public function addDocument(\DOMDocument|\DOM\Document $dom): void
    {
        if ($dom->documentElement) {
            $this->addNode($dom->documentElement);
        }
    }

    /**
     * Adds a \DOMNodeList or a \DOM\NodeList to the list of nodes.
     */
    public function addNodeList(\DOMNodeList|\DOM\NodeList $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof \DOMNode || $node instanceof \DOM\Node) {
                $this->addNode($node);
            }
        }
    }

    /**
     * Adds a \DOMNode or a \DOM\Node instance to the list of nodes.
     */
    public function addNode(\DOMNode|\DOM\Node $node): void
    {
        if ($node instanceof \DOMDocument || $node instanceof \DOM\Document) {
            $node = $node->documentElement;
        }

        if (null !== $this->domDocument && $this->domDocument !== $node->ownerDocument) {
            throw new \InvalidArgumentException('Attaching DOM nodes from multiple documents in the same crawler is forbidden.');
        }

        $this->domDocument ??= $node->ownerDocument;
        if ($node->ownerDocument instanceof \DOMDocument) {
            $this->document = $node->ownerDocument;
        }

        // Don't add duplicate nodes in the Crawler
        if (\in_array($node, $this->nodes, true)) {
            return;
        }

        $this->nodes[] = $node;
    }

    protected function sibling(\DOMNode|\DOM\Node $node, string $siblingDir = 'nextSibling'): array
    {
        $nodes = [];

        $currentNode = $this->getDomNode(0);
        do {
            if ($node !== $currentNode && \XML_ELEMENT_NODE === $node->nodeType) {
                $nodes[] = $node;
            }
        } while ($node = $node->$siblingDir);

        return $nodes;
    }

    private function parseHtml5(string $htmlContent, string $charset = 'UTF-8'): \DOMDocument|\DOM\HTMLDocument
    {
        $htmlContent = $this->convertToHtmlEntities($htmlContent, $charset);

        if ($this->options & self::CRAWLER_USE_NATIVE_PARSER) {
            return \DOM\HTMLDocument::createFromString($htmlContent, \DOM\HTML_NO_DEFAULT_NS);
        }

        return $this->html5Parser->parse($htmlContent);
    }

    private function parseXhtml(string $htmlContent, string $charset = 'UTF-8'): \DOMDocument|\DOM\XMLDocument
    {
        $htmlContent = $this->convertToHtmlEntities($htmlContent, $charset);

        $internalErrors = libxml_use_internal_errors(true);

        if ($this->options & self::CRAWLER_USE_NATIVE_PARSER) {
            try {
                $dom = \DOM\XMLDocument::createFromString($htmlContent);
            } catch (\Exception) {
                // like with legacy nodes, create an empty document if
                // content cannot be loaded
                $dom = \DOM\XMLDocument::createEmpty();
            }
        } else {
            $dom = new \DOMDocument('1.0', $charset);
            $dom->validateOnParse = true;

            if ('' !== trim($htmlContent)) {
                @$dom->loadHTML($htmlContent);
            }
        }

        libxml_use_internal_errors($internalErrors);

        return $dom;
    }

    /**
     * Returns the first node of the list as HTML.
     *
     * @param string|null $default When not null: the value to return when the current node is empty
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function html(?string $default = null): string
    {
        if (!$this->nodes) {
            if (null !== $default) {
                return $default;
            }

            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getDomNode(0);
        $owner = $node->ownerDocument;

        if ($this->options & self::CRAWLER_USE_EXTERNAL_PARSER && '<!DOCTYPE html>' === $owner->saveXML($owner->childNodes[0])) {
            $owner = $this->html5Parser;
        }

        if ($this->options & self::CRAWLER_USE_NATIVE_PARSER && $owner instanceof \DOM\Document) {
            return $owner->saveXML($node);
        }

        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $owner->saveHTML($child);
        }

        return $html;
    }

    /**
     * Parse string into DOMDocument object using HTML5 parser if the content is HTML5 and the library is available.
     * Use libxml parser otherwise.
     */
    private function parseHtmlString(string $content, string $charset): \DOMDocument|\DOM\Document
    {
        if ($this->canParseHtml5String($content)) {
            return $this->parseHtml5($content, $charset);
        }

        return $this->parseXhtml($content, $charset);
    }

    protected function canParseHtml5String(string $content): bool
    {
        if (!($this->options & self::CRAWLER_ENABLE_HTML5_PARSING)) {
            return false;
        }

        if (false === ($pos = stripos($content, '<!doctype html>'))) {
            return false;
        }

        $header = substr($content, 0, $pos);

        return '' === $header || $this->isValidHtml5Heading($header);
    }
}
