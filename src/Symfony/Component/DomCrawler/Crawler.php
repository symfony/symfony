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
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Crawler eases navigation of a list of DOM Node objects.
 *
 * Use DomCrawler instead of this class if you want to use the HTML5 parser and run PHP 8.4 or higher.
 *
 * @template T of \Dom\Node|\DOMNode
 *
 * @implements \IteratorAggregate<int, T>
 *
 * @psalm-type L = (T is \Dom\Node ? \Dom\NodeList<T> : \DOMNodeList<T>)
 *
 * @phpstan-type L (T is \Dom\Node ? \Dom\NodeList<T> : \DOMNodeList<T>)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Crawler implements \Countable, \IteratorAggregate
{
    /**
     * The default namespace prefix to be used with XPath and CSS expressions.
     */
    private string $defaultNamespacePrefix = 'default';

    /**
     * A map of manually registered namespaces.
     *
     * @var array<string, string>
     */
    private array $namespaces = [];

    /**
     * A map of cached namespaces.
     *
     * @var \ArrayObject<string, string|null>
     */
    private \ArrayObject $cachedNamespaces;

    private ?string $baseHref;

    /**
     * @var (T is \Dom\Node ? \Dom\Document : \DOMDocument)|null
     */
    private \Dom\Document|\DOMDocument|null $document = null;

    /**
     * @var list<T>
     */
    private array $nodes = [];

    /**
     * Whether the Crawler contains HTML or XML content (used when converting CSS to XPath).
     */
    private bool $isHtml = true;

    private ?HTML5 $html5Parser = null;

    /**
     * @param \DOMNodeList<\DOMNode>|\DOMNode|\DOMNode[]|string|null $node A Node to use as the base for the crawling
     */
    public function __construct(
        \DOMNodeList|\DOMNode|array|string|null $node = null,
        protected ?string $uri = null,
        ?string $baseHref = null,
        bool $useHtml5Parser = true,
    ) {
        $this->baseHref = $baseHref ?: $uri;
        $this->cachedNamespaces = new \ArrayObject();
        if ($useHtml5Parser) {
            if (\PHP_VERSION_ID >= 80400) {
                trigger_deprecation('symfony/dom-crawler', '7.4', 'Using the "masterminds/html5" parser is deprecated; use the "DomCrawler" class instead.');
                // throw new \LogicException('Use the "DomCrawler" class instead of the "Crawler" one to enable HTML5 parsing.');
            }
            $this->html5Parser = new HTML5(['disable_html_ns' => true]);
        }

        $this->add($node);
    }

    /**
     * Returns the current URI.
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * Returns base href.
     */
    public function getBaseHref(): ?string
    {
        return $this->baseHref;
    }

    /**
     * Removes all the nodes.
     */
    public function clear(): void
    {
        $this->nodes = [];
        $this->document = null;
        $this->cachedNamespaces = new \ArrayObject();
    }

    /**
     * Adds a node to the current list of nodes.
     *
     * This method uses the appropriate specialized add*() method based
     * on the type of the argument.
     *
     * @param L|T|T[]|string|null $node
     */
    public function add(\Dom\NodeList|\Dom\Node|\DOMNodeList|\DOMNode|array|string|null $node): void
    {
        if ($node instanceof \Dom\NodeList || $node instanceof \DOMNodeList) {
            $this->addNodeList($node);
        } elseif ($node instanceof \Dom\Node || $node instanceof \DOMNode) {
            $this->addNode($node);
        } elseif (\is_array($node)) {
            $this->addNodes($node);
        } elseif (\is_string($node)) {
            $this->addContent($node);
        }
    }

    /**
     * Adds HTML/XML content.
     *
     * If the charset is not set via the content type, it is assumed to be UTF-8,
     * or ISO-8859-1 as a fallback, which is the default charset defined by the
     * HTTP 1.1 specification.
     */
    public function addContent(string $content, ?string $type = null): void
    {
        if (!$type) {
            $type = str_starts_with($content, '<?xml') ? 'application/xml' : 'text/html';
        }

        // DOM only for HTML/XML content
        if (!preg_match('/(x|ht)ml/i', $type, $xmlMatches)) {
            return;
        }

        $charset = preg_match('//u', $content) ? 'UTF-8' : 'ISO-8859-1';

        // http://www.w3.org/TR/encoding/#encodings
        // http://www.w3.org/TR/REC-xml/#NT-EncName
        $content = preg_replace_callback('/(charset *= *["\']?)([a-zA-Z\-0-9_:.]+)/i', function ($m) use (&$charset) {
            if ('charset=' === $this->convertToHtmlEntities('charset=', $m[2])) {
                $charset = $m[2];
            }

            return $m[1].$charset;
        }, $content, 1);

        if ('x' === $xmlMatches[1]) {
            $this->addXmlContent($content, $charset);
        } else {
            $this->addHtmlContent($content, $charset);
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
        if (\count($base) && $baseHref) {
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

        if ($this instanceof DomCrawler) {
            $dom = @\Dom\XMLDocument::createFromString($content, $options);
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
     * Adds a DOM Document to the list of nodes.
     *
     * @param (T is \Dom\Node ? \Dom\Document : \DOMDocument) $dom
     */
    public function addDocument(\Dom\Document|\DOMDocument $dom): void
    {
        if ($dom->documentElement) {
            $this->addNode($dom->documentElement);
        }
    }

    /**
     * Adds a DOM NodeList to the list of nodes.
     *
     * @param L $nodes
     */
    public function addNodeList(\Dom\NodeList|\DOMNodeList $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof \Dom\Node || $node instanceof \DOMNode) {
                $this->addNode($node);
            }
        }
    }

    /**
     * Adds an array of DOM Node instances to the list of nodes.
     *
     * @param T[] $nodes
     */
    public function addNodes(array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->add($node);
        }
    }

    /**
     * Adds a DOM Node instance to the list of nodes.
     *
     * @param T $node
     */
    public function addNode(\Dom\Node|\DOMNode $node): void
    {
        if ($node instanceof \Dom\Document || $node instanceof \DOMDocument) {
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
     * Returns a node given its position in the node list.
     */
    public function eq(int $position): static
    {
        if (isset($this->nodes[$position])) {
            return $this->createSubCrawler($this->nodes[$position]);
        }

        return $this->createSubCrawler(null);
    }

    /**
     * Calls an anonymous function on each node of the list.
     *
     * The anonymous function receives the position and the node wrapped
     * in a Crawler instance as arguments.
     *
     * Example:
     *
     *     $crawler->filter('h1')->each(fn ($node, $i) => $node->text());
     *
     * @template R of mixed
     *
     * @param \Closure(static, int):R $closure
     *
     * @return list<R> An array of values returned by the anonymous function
     */
    public function each(\Closure $closure): array
    {
        $data = [];
        foreach ($this->nodes as $i => $node) {
            $data[] = $closure($this->createSubCrawler($node), $i);
        }

        return $data;
    }

    /**
     * Slices the list of nodes by $offset and $length.
     */
    public function slice(int $offset = 0, ?int $length = null): static
    {
        return $this->createSubCrawler(\array_slice($this->nodes, $offset, $length));
    }

    /**
     * Reduces the list of nodes by calling an anonymous function.
     *
     * To remove a node from the list, the anonymous function must return false.
     *
     * @param \Closure(static, int):bool $closure
     */
    public function reduce(\Closure $closure): static
    {
        $nodes = [];
        foreach ($this->nodes as $i => $node) {
            if (false !== $closure($this->createSubCrawler($node), $i)) {
                $nodes[] = $node;
            }
        }

        return $this->createSubCrawler($nodes);
    }

    /**
     * Returns the first node of the current selection.
     */
    public function first(): static
    {
        return $this->eq(0);
    }

    /**
     * Returns the last node of the current selection.
     */
    public function last(): static
    {
        return $this->eq(\count($this->nodes) - 1);
    }

    /**
     * Returns the siblings nodes of the current selection.
     *
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function siblings(): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        return $this->createSubCrawler($this->sibling($this->getNode(0)->parentNode->firstChild));
    }

    public function matches(string $selector): bool
    {
        if (!$this->nodes) {
            return false;
        }

        $converter = $this->createCssSelectorConverter();
        $xpath = $converter->toXPath($selector, 'self::');

        return 0 !== $this->filterRelativeXPath($xpath)->count();
    }

    /**
     * Return first parents (heading toward the document root) of the Element that matches the provided selector.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/closest#Polyfill
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function closest(string $selector): ?static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $domNode = $this->getNode(0);

        while (null !== $domNode && \XML_ELEMENT_NODE === $domNode->nodeType) {
            $node = $this->createSubCrawler($domNode);
            if ($node->matches($selector)) {
                return $node;
            }

            $domNode = $node->getNode(0)->parentNode;
        }

        return null;
    }

    /**
     * Returns the next siblings nodes of the current selection.
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function nextAll(): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        return $this->createSubCrawler($this->sibling($this->getNode(0)));
    }

    /**
     * Returns the previous sibling nodes of the current selection.
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function previousAll(): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        return $this->createSubCrawler($this->sibling($this->getNode(0), 'previousSibling'));
    }

    /**
     * Returns the ancestors of the current selection.
     *
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function ancestors(): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);
        $nodes = [];

        while ($node = $node->parentNode) {
            if (\XML_ELEMENT_NODE === $node->nodeType) {
                $nodes[] = $node;
            }
        }

        return $this->createSubCrawler($nodes);
    }

    /**
     * Returns the children nodes of the current selection.
     *
     * @throws \InvalidArgumentException When the current node is empty
     * @throws \RuntimeException         If the CssSelector Component is not available and $selector is provided
     */
    public function children(?string $selector = null): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        if (null !== $selector) {
            $converter = $this->createCssSelectorConverter();
            $xpath = $converter->toXPath($selector, 'child::');

            return $this->filterRelativeXPath($xpath);
        }

        $node = $this->getNode(0)->firstChild;

        return $this->createSubCrawler($node ? $this->sibling($node) : []);
    }

    /**
     * Returns the attribute value of the first node of the list.
     *
     * @param string|null $default When not null: the value to return when the node or attribute is empty
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function attr(string $attribute, ?string $default = null): ?string
    {
        if (!$this->nodes) {
            if (null !== $default) {
                return $default;
            }

            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);

        return $node->hasAttribute($attribute) ? $node->getAttribute($attribute) : $default;
    }

    /**
     * Returns the node name of the first node of the list.
     *
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function nodeName(): string
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        return $this->isHtml ? strtolower($this->getNode(0)->nodeName) : $this->getNode(0)->nodeName;
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
        $text = $node instanceof \Dom\Node ? ($node->textContent ?? '') : $node->nodeValue;

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
            $content = $childNode instanceof \Dom\Node ? ($childNode->textContent ?? '') : $childNode->nodeValue;
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
     * @throws \InvalidArgumentException When the current node is empty
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

        if ($this->html5Parser && '<!DOCTYPE html>' === $owner->saveXML($owner->childNodes[0])) {
            $owner = $this->html5Parser;
        }

        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $owner->saveHTML($child);
        }

        if ($this->document instanceof \Dom\HTMLDocument) {
            // remove all void elements as defined by HTML5
            $html = str_replace(['</area>', '</base>', '</br>', '</col>', '</embed>', '</hr>', '</img>', '</input>', '</link>', '</meta>', '</source>', '</track>', '</wbr>'], '', $html);
        }

        return $html;
    }

    /**
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function outerHtml(): string
    {
        if (!\count($this)) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);
        $owner = $node->ownerDocument;

        if ($this->html5Parser && '<!DOCTYPE html>' === $owner->saveXML($owner->childNodes[0])) {
            $owner = $this->html5Parser;
        }

        return $owner->saveHTML($node);
    }

    /**
     * Evaluates an XPath expression.
     *
     * Since an XPath expression might evaluate to either a simple type or a DOM NodeList,
     * this method will return either an array of simple types or a new Crawler instance.
     */
    public function evaluate(string $xpath): array|static
    {
        if (null === $this->document) {
            throw new \LogicException('Cannot evaluate the expression on an uninitialized crawler.');
        }

        $data = [];
        $domxpath = $this->createDOMXPath($this->document, $this->findNamespacePrefixes($xpath));

        foreach ($this->nodes as $node) {
            $data[] = $domxpath->evaluate($xpath, $node);
        }

        if (isset($data[0]) && ($data[0] instanceof \Dom\NodeList || $data[0] instanceof \DOMNodeList)) {
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
                    $elements[] = $node instanceof \Dom\Node ? ($node->textContent ?? '') : $node->nodeValue;
                } elseif ('_name' === $attribute) {
                    $elements[] = $this->isHtml ? strtolower($node->nodeName) : $node->nodeName;
                } else {
                    $elements[] = $node->getAttribute($attribute) ?? '';
                }
            }

            $data[] = 1 === $count ? $elements[0] : $elements;
        }

        return $data;
    }

    /**
     * Filters the list of nodes with an XPath expression.
     *
     * The XPath expression is evaluated in the context of the crawler, which
     * is considered as a fake parent of the elements inside it.
     * This means that a child selector "div" or "./div" will match only
     * the div elements of the current crawler, not their children.
     */
    public function filterXPath(string $xpath): static
    {
        $xpath = $this->relativize($xpath);

        // If we dropped all expressions in the XPath while preparing it, there would be no match
        if ('' === $xpath) {
            return $this->createSubCrawler(null);
        }

        return $this->filterRelativeXPath($xpath);
    }

    /**
     * Filters the list of nodes with a CSS selector.
     *
     * This method only works if you have installed the CssSelector Symfony Component.
     *
     * @throws \LogicException if the CssSelector Component is not available
     */
    public function filter(string $selector): static
    {
        $converter = $this->createCssSelectorConverter();

        // The CssSelector already prefixes the selector with descendant-or-self::
        return $this->filterRelativeXPath($converter->toXPath($selector));
    }

    /**
     * Selects links by name or alt value for clickable images.
     */
    public function selectLink(string $value): static
    {
        return $this->filterRelativeXPath(
            \sprintf('descendant-or-self::a[contains(concat(\' \', normalize-space(string(.)), \' \'), %1$s) or ./img[contains(concat(\' \', normalize-space(string(@alt)), \' \'), %1$s)]]', static::xpathLiteral(' '.$value.' '))
        );
    }

    /**
     * Selects images by alt value.
     */
    public function selectImage(string $value): static
    {
        $xpath = \sprintf('descendant-or-self::img[contains(normalize-space(string(@alt)), %s)]', static::xpathLiteral($value));

        return $this->filterRelativeXPath($xpath);
    }

    /**
     * Selects a button by its text content, id, value, name or alt attribute.
     */
    public function selectButton(string $value): static
    {
        return $this->filterRelativeXPath(
            \sprintf('descendant-or-self::input[((contains(%1$s, "submit") or contains(%1$s, "button")) and contains(concat(\' \', normalize-space(string(@value)), \' \'), %2$s)) or (contains(%1$s, "image") and contains(concat(\' \', normalize-space(string(@alt)), \' \'), %2$s)) or @id=%3$s or @name=%3$s] | descendant-or-self::button[contains(concat(\' \', normalize-space(string(.)), \' \'), %2$s) or contains(concat(\' \', normalize-space(string(@value)), \' \'), %2$s) or @id=%3$s or @name=%3$s]', 'translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")', static::xpathLiteral(' '.$value.' '), static::xpathLiteral($value))
        );
    }

    /**
     * Returns a Link object for the first node in the list.
     *
     * @return Link<(T is \Dom\Node ? \Dom\Element : \DOMElement)>
     *
     * @throws \InvalidArgumentException If the current node list is empty or the selected node is not a DOM Element
     */
    public function link(string $method = 'get'): Link
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);

        if (!$node instanceof \Dom\Element && !$node instanceof \DOMElement) {
            throw new \InvalidArgumentException(\sprintf('The selected node should be a DOM Element, got "%s".', get_debug_type($node)));
        }

        return new Link($node, $this->baseHref, $method);
    }

    /**
     * Returns an array of Link objects for the nodes in the list.
     *
     * @return Link<(T is \Dom\Node ? \Dom\Element : \DOMElement)>[]
     *
     * @throws \InvalidArgumentException If the current node list contains non-DOM Element instances
     */
    public function links(): array
    {
        $links = [];
        foreach ($this->nodes as $node) {
            if (!$node instanceof \Dom\Element && !$node instanceof \DOMElement) {
                throw new \InvalidArgumentException(\sprintf('The current node list should contain only DOM Element instances, "%s" found.', get_debug_type($node)));
            }

            $links[] = new Link($node, $this->baseHref, 'get');
        }

        return $links;
    }

    /**
     * Returns an Image object for the first node in the list.
     *
     * @return Image<(T is \Dom\Node ? \Dom\Element : \DOMElement)>
     *
     * @throws \InvalidArgumentException If the current node list is empty
     */
    public function image(): Image
    {
        if (!\count($this)) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);

        if (!$node instanceof \Dom\Element && !$node instanceof \DOMElement) {
            throw new \InvalidArgumentException(\sprintf('The selected node should be a DOM Element, got "%s".', get_debug_type($node)));
        }

        return new Image($node, $this->baseHref);
    }

    /**
     * Returns an array of Image objects for the nodes in the list.
     *
     * @return Image<(T is \Dom\Node ? \Dom\Element : \DOMElement)>[]
     */
    public function images(): array
    {
        $images = [];
        foreach ($this as $node) {
            if (!$node instanceof \Dom\Element && !$node instanceof \DOMElement) {
                throw new \InvalidArgumentException(\sprintf('The current node list should contain only DOM Element instances, "%s" found.', get_debug_type($node)));
            }

            $images[] = new Image($node, $this->baseHref);
        }

        return $images;
    }

    /**
     * Returns a Form object for the first node in the list.
     *
     * @return Form<(T is \Dom\Node ? \Dom\Element : \DOMElement)>
     *
     * @throws \InvalidArgumentException If the current node list is empty or the selected node is not instance of a DOM Element
     */
    public function form(?array $values = null, ?string $method = null): Form
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);

        if (!$node instanceof \Dom\Element && !$node instanceof \DOMElement) {
            throw new \InvalidArgumentException(\sprintf('The selected node should be a DOM Element, got "%s".', get_debug_type($node)));
        }

        $form = new Form($node, $this->uri, $method, $this->baseHref);

        if (null !== $values) {
            $form->setValues($values);
        }

        return $form;
    }

    /**
     * Overloads a default namespace prefix to be used with XPath and CSS expressions.
     */
    public function setDefaultNamespacePrefix(string $prefix): void
    {
        $this->defaultNamespacePrefix = $prefix;
    }

    public function registerNamespace(string $prefix, string $namespace): void
    {
        $this->namespaces[$prefix] = $namespace;
    }

    /**
     * Converts string for XPath expressions.
     *
     * Escaped characters are: quotes (") and apostrophe (').
     *
     *  Examples:
     *
     *     echo Crawler::xpathLiteral('foo " bar');
     *     //prints 'foo " bar'
     *
     *     echo Crawler::xpathLiteral("foo ' bar");
     *     //prints "foo ' bar"
     *
     *     echo Crawler::xpathLiteral('a\'b"c');
     *     //prints concat('a', "'", 'b"c')
     */
    public static function xpathLiteral(string $s): string
    {
        if (!str_contains($s, "'")) {
            return \sprintf("'%s'", $s);
        }

        if (!str_contains($s, '"')) {
            return \sprintf('"%s"', $s);
        }

        $string = $s;
        $parts = [];
        while (true) {
            if (false !== $pos = strpos($string, "'")) {
                $parts[] = \sprintf("'%s'", substr($string, 0, $pos));
                $parts[] = "\"'\"";
                $string = substr($string, $pos + 1);
            } else {
                $parts[] = "'$string'";
                break;
            }
        }

        return \sprintf('concat(%s)', implode(', ', $parts));
    }

    /**
     * Filters the list of nodes with an XPath expression.
     *
     * The XPath expression should already be processed to apply it in the context of each node.
     */
    private function filterRelativeXPath(string $xpath): static
    {
        $crawler = $this->createSubCrawler(null);
        if (null === $this->document) {
            return $crawler;
        }

        $domxpath = $this->createDOMXPath($this->document, $this->findNamespacePrefixes($xpath));

        foreach ($this->nodes as $node) {
            try {
                $nodes = $domxpath->query($xpath, $node);
            } catch (\DOMException $e) {
                if (str_starts_with($e->getMessage(), 'The namespace axis is not well-defined in the living DOM specification.')) {
                    continue;
                }
                throw $e;
            }
            $crawler->add($nodes);
        }

        return $crawler;
    }

    /**
     * Make the XPath relative to the current context.
     *
     * The returned XPath will match elements matching the XPath inside the current crawler
     * when running in the context of a node of the crawler.
     */
    private function relativize(string $xpath): string
    {
        $expressions = [];

        // An expression which will never match to replace expressions which cannot match in the crawler
        // We cannot drop
        $nonMatchingExpression = 'a[name() = "b"]';

        $xpathLen = \strlen($xpath);
        $openedBrackets = 0;
        $startPosition = strspn($xpath, " \t\n\r\0\x0B");

        for ($i = $startPosition; $i <= $xpathLen; ++$i) {
            $i += strcspn($xpath, '"\'[]|', $i);

            if ($i < $xpathLen) {
                switch ($xpath[$i]) {
                    case '"':
                    case "'":
                        if (false === $i = strpos($xpath, $xpath[$i], $i + 1)) {
                            return $xpath; // The XPath expression is invalid
                        }
                        continue 2;
                    case '[':
                        ++$openedBrackets;
                        continue 2;
                    case ']':
                        --$openedBrackets;
                        continue 2;
                }
            }
            if ($openedBrackets) {
                continue;
            }

            if ($startPosition < $xpathLen && '(' === $xpath[$startPosition]) {
                // If the union is inside some braces, we need to preserve the opening braces and apply
                // the change only inside it.
                $j = 1 + strspn($xpath, "( \t\n\r\0\x0B", $startPosition + 1);
                $parenthesis = substr($xpath, $startPosition, $j);
                $startPosition += $j;
            } else {
                $parenthesis = '';
            }
            $expression = rtrim(substr($xpath, $startPosition, $i - $startPosition));

            if (str_starts_with($expression, 'self::*/')) {
                $expression = './'.substr($expression, 8);
            }

            // add prefix before absolute element selector
            if ('' === $expression) {
                $expression = $nonMatchingExpression;
            } elseif (str_starts_with($expression, '//')) {
                $expression = 'descendant-or-self::'.substr($expression, 2);
            } elseif (str_starts_with($expression, './/')) {
                $expression = 'descendant-or-self::'.substr($expression, 3);
            } elseif (str_starts_with($expression, './')) {
                $expression = 'self::'.substr($expression, 2);
            } elseif (str_starts_with($expression, 'child::')) {
                $expression = 'self::'.substr($expression, 7);
            } elseif ('/' === $expression[0] || '.' === $expression[0] || str_starts_with($expression, 'self::')) {
                $expression = $nonMatchingExpression;
            } elseif (str_starts_with($expression, 'descendant::')) {
                $expression = 'descendant-or-self::'.substr($expression, 12);
            } elseif (preg_match('/^(ancestor|ancestor-or-self|attribute|following|following-sibling|namespace|parent|preceding|preceding-sibling)::/', $expression)) {
                // the fake root has no parent, preceding or following nodes and also no attributes (even no namespace attributes)
                $expression = $nonMatchingExpression;
            } elseif (!str_starts_with($expression, 'descendant-or-self::')) {
                $expression = 'self::'.$expression;
            }
            $expressions[] = $parenthesis.$expression;

            if ($i === $xpathLen) {
                return implode(' | ', $expressions);
            }

            $i += strspn($xpath, " \t\n\r\0\x0B", $i + 1);
            $startPosition = $i + 1;
        }

        return $xpath; // The XPath expression is invalid
    }

    /**
     * @return T|null
     */
    public function getNode(int $position): \Dom\Node|\DOMNode|null
    {
        return $this->nodes[$position] ?? null;
    }

    public function count(): int
    {
        return \count($this->nodes);
    }

    /**
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->nodes);
    }

    /**
     * @param T $node
     */
    protected function sibling(\Dom\Node|\DOMNode $node, string $siblingDir = 'nextSibling'): array
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

    private function parseHtml5(string $htmlContent, string $charset = 'UTF-8'): \DOMDocument
    {
        if (!$this->supportsEncoding($charset)) {
            $htmlContent = $this->convertToHtmlEntities($htmlContent, $charset);
            $charset = 'UTF-8';
        }

        return $this->html5Parser->parse($htmlContent, ['encoding' => $charset]);
    }

    private function supportsEncoding(string $encoding): bool
    {
        try {
            return '' === @mb_convert_encoding('', $encoding, 'UTF-8');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return (T is \Dom\Node ? \Dom\XMLDocument : \DOMDocument)
     */
    private function parseXhtml(string $htmlContent, string $charset = 'UTF-8'): \Dom\XMLDocument|\DOMDocument
    {
        if ('UTF-8' === $charset && preg_match('//u', $htmlContent)) {
            $htmlContent = '<?xml encoding="UTF-8">'.$htmlContent;
        } else {
            $htmlContent = $this->convertToHtmlEntities($htmlContent, $charset);
        }

        $internalErrors = libxml_use_internal_errors(true);

        if ($this instanceof DomCrawler) {
            $dom = @\Dom\XMLDocument::createFromString($htmlContent);
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
     * Converts charset to HTML-entities to ensure valid parsing.
     */
    private function convertToHtmlEntities(string $htmlContent, string $charset = 'UTF-8'): string
    {
        set_error_handler(static fn () => throw new \Exception());

        try {
            return mb_encode_numericentity($htmlContent, [0x80, 0x10FFFF, 0, 0x1FFFFF], $charset);
        } catch (\Exception|\ValueError) {
            try {
                $htmlContent = iconv($charset, 'UTF-8', $htmlContent);
                $htmlContent = mb_encode_numericentity($htmlContent, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
            } catch (\Exception|\ValueError) {
            }

            return $htmlContent;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param (T is \Dom\Node ? \Dom\Document : \DOMDocument) $document
     *
     * @return (T is \Dom\Node ? \Dom\XPath : \DOMXPath)
     *
     * @throws \InvalidArgumentException
     */
    private function createDOMXPath(\Dom\Document|\DOMDocument $document, array $prefixes = []): \Dom\XPath|\DOMXPath
    {
        $domxpath = $document instanceof \DOMDocument ? new \DOMXPath($document) : new \Dom\XPath($document);

        foreach ($prefixes as $prefix) {
            $namespace = $this->discoverNamespace($domxpath, $prefix);
            if (null !== $namespace) {
                $domxpath->registerNamespace($prefix, $namespace);
            }
        }

        if (!$this->document?->documentElement instanceof \Dom\Node) {
            return $domxpath;
        }

        foreach ($domxpath->document->documentElement->getInScopeNamespaces() as $namespace) {
            $domxpath->registerNamespace($namespace->prefix ?? $this->defaultNamespacePrefix, $namespace->namespaceURI);
        }

        return $domxpath;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function discoverNamespace(\Dom\XPath|\DOMXPath $domxpath, string $prefix): ?string
    {
        if (\array_key_exists($prefix, $this->namespaces)) {
            return $this->namespaces[$prefix];
        }

        if ($this->cachedNamespaces->offsetExists($prefix)) {
            return $this->cachedNamespaces[$prefix];
        }

        // ask for one namespace, otherwise we'd get a collection with an item for each node
        $namespaces = $domxpath->query(\sprintf('(//namespace::*[name()="%s"])[last()]', $this->defaultNamespacePrefix === $prefix ? '' : $prefix));

        return $this->cachedNamespaces[$prefix] = $namespaces->item(0)?->nodeValue;
    }

    private function findNamespacePrefixes(string $xpath): array
    {
        if (preg_match_all('/(?P<prefix>[a-z_][a-z_0-9\-\.]*+):[^"\/:]/i', $xpath, $matches)) {
            return array_unique($matches['prefix']);
        }

        return [];
    }

    /**
     * Creates a crawler for some subnodes.
     *
     * @param L|T|T[]|string|null $nodes
     */
    private function createSubCrawler(\Dom\NodeList|\Dom\Node|\DOMNodeList|\DOMNode|array|string|null $nodes): static
    {
        $crawler = new static($nodes, $this->uri, $this->baseHref, false);
        $crawler->isHtml = $this->isHtml;
        $crawler->document = $this->document;
        $crawler->namespaces = $this->namespaces;
        $crawler->cachedNamespaces = $this->cachedNamespaces;
        $crawler->html5Parser = $this->html5Parser;

        return $crawler;
    }

    /**
     * @throws \LogicException If the CssSelector Component is not available
     */
    private function createCssSelectorConverter(): CssSelectorConverter
    {
        if (!class_exists(CssSelectorConverter::class)) {
            throw new \LogicException('To filter with a CSS selector, install the CssSelector component ("composer require symfony/css-selector"). Or use filterXpath instead.');
        }

        return new CssSelectorConverter($this->isHtml);
    }

    /**
     * Parse string into DOM Document object.
     *
     * @return (T is \Dom\Node ? \Dom\Document : \DOMDocument)
     */
    private function parseHtmlString(string $content, string $charset): \Dom\Document|\DOMDocument
    {
        if ($this instanceof DomCrawler) {
            return @\Dom\HTMLDocument::createFromString($content, \Dom\HTML_NO_DEFAULT_NS, $charset);
        }

        if ($this->canParseHtml5String($content)) {
            return $this->parseHtml5($content, $charset);
        }

        return $this->parseXhtml($content, $charset);
    }

    private function canParseHtml5String(string $content): bool
    {
        if (!$this->html5Parser) {
            return false;
        }

        if (false === $pos = stripos($content, '<!doctype html>')) {
            return false;
        }

        $header = substr($content, 0, $pos);

        return '' === $header || $this->isValidHtml5Heading($header);
    }

    private function isValidHtml5Heading(string $heading): bool
    {
        return 1 === preg_match('/^\x{FEFF}?\s*(<!--[^>]*?-->\s*)*$/u', $heading);
    }

    private function normalizeWhitespace(string $string): string
    {
        return trim(preg_replace("/(?:[ \n\r\t\x0C]{2,}+|[\n\r\t\x0C])/", ' ', $string), " \n\r\t\x0C");
    }
}
