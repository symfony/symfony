<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\NativeCrawler;

use Symfony\Component\DomCrawler\CrawlerTrait;

/**
 * Crawler eases navigation of a list of \DOM\Node objects.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @implements \IteratorAggregate<int, \DOM\Node>
 */
final class DomCrawler implements \Countable, \IteratorAggregate
{
    use CrawlerTrait;

    private ?\DOM\Document $document = null;

    /**
     * @var list<\DOM\Node>
     */
    private array $nodes = [];

    /**
     * @param \DOM\NodeList|\DOM\Node|\DOM\Node[]|string|null $node A Node to use as the base for the crawling
     */
    public function __construct(
        \DOM\NodeList|\DOM\Node|array|string|null $node = null,
        private ?string $uri = null,
        ?string $baseHref = null,
    ) {
        if (\PHP_VERSION_ID < 80400) {
            throw new \LogicException('The DomCrawler class requires PHP 8.4 or higher.');
        }

        $this->baseHref = $baseHref ?: $uri;
        $this->cachedNamespaces = new \ArrayObject();

        $this->add($node);
    }

    /**
     * Adds a node to the current list of nodes.
     *
     * This method uses the appropriate specialized add*() method based
     * on the type of the argument.
     *
     * @param \DOM\NodeList|\DOM\Node|\DOM\Node[]|string|null $node A node
     *
     * @throws \InvalidArgumentException when node is not the expected type
     */
    public function add(\DOM\NodeList|\DOM\Node|array|string|null $node): void
    {
        if ($node instanceof \DOM\NodeList) {
            $this->addNodeList($node);
        } elseif ($node instanceof \DOM\Node) {
            $this->addNode($node);
        } elseif (\is_array($node)) {
            $this->addNodes($node);
        } elseif (\is_string($node)) {
            $this->addContent($node);
        } elseif (null !== $node) {
            throw new \InvalidArgumentException(\sprintf('Expecting a DOM\NodeList or DOM\Node instance, an array, a string, or null, but got "%s".', get_debug_type($node)));
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

        try {
            $dom = \DOM\XMLDocument::createFromString($content, $options);
        } catch (\Exception) {
            $dom = \DOM\XMLDocument::createEmpty();
        }

        libxml_use_internal_errors($internalErrors);

        $this->addDocument($dom);

        $this->isHtml = false;
    }

    /**
     * Adds a \DOM\Document to the list of nodes.
     */
    public function addDocument(\DOM\Document $dom): void
    {
        if ($dom->documentElement) {
            $this->addNode($dom->documentElement);
        }
    }

    /**
     * Adds a \DOM\NodeList to the list of nodes.
     */
    public function addNodeList(\DOM\NodeList $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof \DOM\Node) {
                $this->addNode($node);
            }
        }
    }

    /**
     * Adds an array of \DOM\Node instances to the list of nodes.
     *
     * @param \DOM\Node[] $nodes An array of \DOM\Node instances
     */
    public function addNodes(array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->add($node);
        }
    }

    /**
     * Adds a \DOM\Node instance to the list of nodes.
     */
    public function addNode(\DOM\Node $node): void
    {
        if ($node instanceof \DOM\Document) {
            $node = $node->documentElement;
        }

        if (null !== $this->document && $this->document !== $node->ownerDocument) {
            throw new \InvalidArgumentException('Attaching DOM nodes from multiple documents in the same crawler is forbidden.');
        }

        $this->document ??= $node->ownerDocument;

        // Don't add duplicate nodes in the Crawler
        if (\in_array($node, $this->nodes, true)) {
            return;
        }

        $this->nodes[] = $node;
    }

    /**
     * Returns the text of the first node of the list.
     *
     * Pass true as the second argument to normalize whitespaces.
     *
     * @param string|null $default             When not null: the value to return when the current node is empty
     * @param bool        $normalizeWhitespace Whether whitespaces should be trimmed and normalized to single spaces
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function text(?string $default = null, bool $normalizeWhitespace = true): string
    {
        if (!$this->nodes) {
            if (null !== $default) {
                return $default;
            }

            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);
        $text = $node->nodeValue ?? $node->textContent;

        if ($normalizeWhitespace) {
            return $this->normalizeWhitespace($text);
        }

        return $text;
    }

    /**
     * Returns only the inner text that is the direct descendent of the current node, excluding any child nodes.
     *
     * @param bool $normalizeWhitespace Whether whitespaces should be trimmed and normalized to single spaces
     */
    public function innerText(bool $normalizeWhitespace = true): string
    {
        foreach ($this->getNode(0)->childNodes as $childNode) {
            if (\XML_TEXT_NODE !== $childNode->nodeType && \XML_CDATA_SECTION_NODE !== $childNode->nodeType) {
                continue;
            }
            $content = $childNode->nodeValue ?? $childNode->textContent;
            if (!$normalizeWhitespace) {
                return $content;
            }
            if ('' !== trim($content)) {
                return $this->normalizeWhitespace($content);
            }
        }

        return '';
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

        $node = $this->getNode(0);
        $owner = $node->ownerDocument;

        if ($owner instanceof \DOM\XMLDocument) {
            return $owner->saveXML($node);
        }

        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $owner->saveHTML($child);
        }

        return $html;
    }

    public function outerHtml(): string
    {
        if (!\count($this)) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);
        $owner = $node->ownerDocument;

        return $owner->saveHTML($node);
    }

    /**
     * Evaluates an XPath expression.
     *
     * Since an XPath expression might evaluate to either a simple type or a \DOM\NodeList,
     * this method will return either an array of simple types or a new Crawler instance.
     */
    public function evaluate(string $xpath): array|self
    {
        if (null === $this->document) {
            throw new \LogicException('Cannot evaluate the expression on an uninitialized crawler.');
        }

        $data = [];
        $domxpath = $this->createDOMXPath($this->document, $this->findNamespacePrefixes($xpath));

        foreach ($this->nodes as $node) {
            $data[] = $domxpath->evaluate($xpath, $node);
        }

        if (isset($data[0]) && $data[0] instanceof \DOM\NodeList) {
            return $this->createSubCrawler($data);
        }

        return $data;
    }

    /**
     * Extracts information from the list of nodes.
     *
     * You can extract attributes or/and the node value (_text).
     *
     * Example:
     *
     *     $crawler->filter('h1 a')->extract(['_text', 'href']);
     */
    public function extract(array $attributes): array
    {
        $count = \count($attributes);

        $data = [];
        foreach ($this->nodes as $node) {
            $elements = [];
            foreach ($attributes as $attribute) {
                if ('_text' === $attribute) {
                    $elements[] = $node->nodeValue ?? $node->textContent;
                } elseif ('_name' === $attribute) {
                    $elements[] = $node->nodeName;
                } else {
                    $elements[] = $node->getAttribute($attribute) ?? '';
                }
            }

            $data[] = 1 === $count ? $elements[0] : $elements;
        }

        return $data;
    }

    /**
     * Returns a Link object for the first node in the list.
     *
     * @throws \InvalidArgumentException If the current node list is empty or the selected node is not instance of DOMElement
     */
    public function link(string $method = 'get'): Link
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);

        if (!$node instanceof \DOM\Element) {
            throw new \InvalidArgumentException(\sprintf('The selected node should be instance of "DOM\Element", got "%s".', get_debug_type($node)));
        }

        return new Link($node, $this->baseHref, $method);
    }

    /**
     * Returns an array of Link objects for the nodes in the list.
     *
     * @return Link[]
     *
     * @throws \InvalidArgumentException If the current node list contains non-DOMElement instances
     */
    public function links(): array
    {
        $links = [];

        foreach ($this->nodes as $node) {
            if (!$node instanceof \DOM\Element) {
                throw new \InvalidArgumentException(\sprintf('The current node list should contain only DOM\Element instances, "%s" found.', get_debug_type($node)));
            }

            $links[] = new Link($node, $this->baseHref, 'get');
        }

        return $links;
    }

    /**
     * Returns an Image object for the first node in the list.
     *
     * @throws \InvalidArgumentException If the current node list is empty
     */
    public function image(): Image
    {
        if (!\count($this)) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);

        if (!$node instanceof \DOM\Element) {
            throw new \InvalidArgumentException(\sprintf('The selected node should be instance of "DOM\Element", got "%s".', get_debug_type($node)));
        }

        return new Image($node, $this->baseHref);
    }

    /**
     * Returns an array of Image objects for the nodes in the list.
     *
     * @return Image[]
     */
    public function images(): array
    {
        $images = [];
        foreach ($this as $node) {
            if (!$node instanceof \DOM\Element) {
                throw new \InvalidArgumentException(\sprintf('The current node list should contain only DOM\Element instances, "%s" found.', get_debug_type($node)));
            }

            $images[] = new Image($node, $this->baseHref);
        }

        return $images;
    }

    /**
     * Returns a Form object for the first node in the list.
     *
     * @throws \InvalidArgumentException If the current node list is empty or the selected node is not instance of DOMElement
     */
    public function form(?array $values = null, ?string $method = null): Form
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);

        if (!$node instanceof \DOM\Element) {
            throw new \InvalidArgumentException(\sprintf('The selected node should be instance of "DOM\Element", got "%s".', get_debug_type($node)));
        }

        $form = new Form($node, $this->uri, $method, $this->baseHref);

        if (null !== $values) {
            $form->setValues($values);
        }

        return $form;
    }

    public function getNode(int $position): ?\DOM\Node
    {
        return $this->nodes[$position] ?? null;
    }

    public function count(): int
    {
        return \count($this->nodes);
    }

    /**
     * @return \ArrayIterator<int, \DOM\Node>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->nodes);
    }

    private function sibling(\DOM\Node $node, string $siblingDir = 'nextSibling'): array
    {
        $nodes = [];

        $currentNode = $this->getNode(0);
        do {
            if ($node !== $currentNode && \XML_ELEMENT_NODE === $node->nodeType) {
                $nodes[] = $node;
            }
        } while ($node = $node->$siblingDir);

        return $nodes;
    }

    private function parseHtml5(string $htmlContent, string $charset = 'UTF-8'):  \DOM\HTMLDocument
    {
        return \DOM\HTMLDocument::createFromString($htmlContent, \DOM\HTML_NO_DEFAULT_NS, $charset);
    }

    private function parseXhtml(string $htmlContent, string $charset = 'UTF-8'): \DOM\XMLDocument
    {
        if ('UTF-8' === $charset && preg_match('//u', $htmlContent)) {
            $htmlContent = '<?xml encoding="UTF-8">'.$htmlContent;
        } else {
            $htmlContent = $this->convertToHtmlEntities($htmlContent, $charset);
        }

        $internalErrors = libxml_use_internal_errors(true);

        try {
            $dom = \DOM\XMLDocument::createFromString($htmlContent);
        } catch (\Exception) {
            // like with legacy nodes, create an empty document if
            // content cannot be loaded
            $dom = \DOM\XMLDocument::createEmpty();
        }

        libxml_use_internal_errors($internalErrors);

        return $dom;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function createDOMXPath(\DOM\Document $document, array $prefixes = []): \DOM\XPath
    {
        $domxpath = new \DOM\XPath($document);
        $this->registerKnownNamespacesInXPath($domxpath);

        return $domxpath;
    }

    private function registerKnownNamespacesInXPath(\DOM\XPath $domxpath): void
    {
        foreach ($this->namespaces as $prefix => $namespace) {
            $domxpath->registerNamespace($prefix, $namespace);
        }

        foreach ($this->cachedNamespaces as $prefix => $namespace) {
            $domxpath->registerNamespace($prefix, $namespace);
        }

        if (null === $this->document) {
            return;
        }

        foreach ($domxpath->document->firstElementChild->getInScopeNamespaces() as $namespace) {
            if (null !== $namespace->prefix) {
                $domxpath->registerNamespace($namespace->prefix, $namespace->namespaceURI);
            } else {
                $domxpath->registerNamespace($this->defaultNamespacePrefix, $namespace->namespaceURI);
            }
        }
    }

    /**
     * Creates a crawler for some subnodes.
     *
     * @param \DOM\NodeList|\DOM\Node|\DOM\Node[]|\string|null $nodes
     */
    private function createSubCrawler(\DOM\NodeList|\DOM\Node|array|string|null $nodes): static
    {
        $crawler = new static($nodes, $this->uri, $this->baseHref);
        $crawler->isHtml = $this->isHtml;
        $crawler->document = $this->document;
        $crawler->namespaces = $this->namespaces;
        $crawler->cachedNamespaces = $this->cachedNamespaces;

        return $crawler;
    }

    /**
     * Parse string into DOMDocument object using HTML5 parser if the content is HTML5 and the library is available.
     * Use libxml parser otherwise.
     */
    private function parseHtmlString(string $content, string $charset): \DOM\Document
    {
        if ($this->canParseHtml5String($content)) {
            return $this->parseHtml5($content, $charset);
        }

        return $this->parseXhtml($content, $charset);
    }

    private function canParseHtml5String(string $content): bool
    {
        if (false === ($pos = stripos($content, '<!doctype html>'))) {
            return false;
        }

        $header = substr($content, 0, $pos);

        return '' === $header || $this->isValidHtml5Heading($header);
    }
}
