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
 * HtmlCrawler eases navigation of a list of \Dom\Node objects.
 *
 * Unlike Crawler, it holds the document parsed by the native HTML5 parser and
 * selects with the selector engine of that same parser. CSS selectors are
 * therefore handled by the engine itself instead of being translated to XPath,
 * which supports the whole selector syntax the engine knows.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @implements \IteratorAggregate<int, \Dom\Node>
 */
class HtmlCrawler implements \Countable, \IteratorAggregate
{
    /**
     * The namespace the HTML parser puts elements in. XPath 1.0 has no notion
     * of a default namespace, so unprefixed name tests never match those
     * elements; expressions are rewritten to use self::XPATH_PREFIX instead.
     */
    private const HTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';
    private const XPATH_PREFIX = 'html';

    private ?string $baseHref;
    private ?\Dom\Document $document = null;

    /**
     * @var array<int, \Dom\Node>
     */
    private array $nodes = [];

    /**
     * @param \Dom\NodeList|\Dom\Node|\Dom\Node[]|string|null $node A Node to use as the base for the crawling
     */
    public function __construct(
        \Dom\NodeList|\Dom\Node|array|string|null $node = null,
        protected ?string $uri = null,
        ?string $baseHref = null,
    ) {
        $this->baseHref = $baseHref ?: $uri;

        $this->add($node);
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

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
    }

    /**
     * Adds a node to the current list of nodes.
     *
     * This method uses the appropriate specialized add*() method based
     * on the type of the argument.
     *
     * @param \Dom\NodeList|\Dom\Node|\Dom\Node[]|string|null $node
     */
    public function add(\Dom\NodeList|\Dom\Node|array|string|null $node): void
    {
        if ($node instanceof \Dom\NodeList) {
            $this->addNodeList($node);
        } elseif ($node instanceof \Dom\Node) {
            $this->addNode($node);
        } elseif (\is_array($node)) {
            $this->addNodes($node);
        } elseif (\is_string($node)) {
            $this->addHtmlContent($node);
        }
    }

    /**
     * Adds an HTML document.
     */
    public function addHtmlContent(string $content, string $charset = 'UTF-8'): void
    {
        $this->addDocument($this->parseHtml($content, $charset));
    }

    /**
     * Adds a \Dom\Document to the list of nodes.
     */
    public function addDocument(\Dom\Document $dom): void
    {
        if ($dom->documentElement) {
            $this->addNode($dom->documentElement);
        }
    }

    public function addNodeList(\Dom\NodeList $nodes): void
    {
        foreach ($nodes as $node) {
            $this->addNode($node);
        }
    }

    /**
     * @param \Dom\Node[] $nodes
     */
    public function addNodes(array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->add($node);
        }
    }

    public function addNode(\Dom\Node $node): void
    {
        if ($node instanceof \Dom\Document) {
            $node = $node->documentElement;

            if (null === $node) {
                return;
            }
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
     * in an HtmlCrawler instance as arguments.
     *
     * @template T
     *
     * @param \Closure(static, int): T $closure
     *
     * @return list<T>
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
     * @throws \InvalidArgumentException When current node is empty
     */
    public function siblings(): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        return $this->createSubCrawler($this->sibling($this->getNode(0)->parentNode->firstChild));
    }

    /**
     * Checks whether the first node of the list matches the given selector.
     */
    public function matches(string $selector): bool
    {
        if (!$this->nodes) {
            return false;
        }

        $node = $this->getNode(0);

        return $node instanceof \Dom\Element && $node->matches($selector);
    }

    /**
     * Return first parents (heading toward the document root) of the Element that matches the provided selector.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/closest
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function closest(string $selector): ?static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);

        if (!$node instanceof \Dom\Element) {
            return null;
        }

        return ($node = $node->closest($selector)) ? $this->createSubCrawler($node) : null;
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
            if ($node instanceof \Dom\Element) {
                $nodes[] = $node;
            }
        }

        return $this->createSubCrawler($nodes);
    }

    /**
     * Returns the children nodes of the current selection.
     *
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function children(?string $selector = null): static
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0)->firstChild;
        $nodes = $node ? $this->sibling($node) : [];

        if (null !== $selector) {
            $nodes = array_values(array_filter($nodes, static fn (\Dom\Node $n) => $n instanceof \Dom\Element && $n->matches($selector)));
        }

        return $this->createSubCrawler($nodes);
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

        return $node instanceof \Dom\Element && $node->hasAttribute($attribute) ? $node->getAttribute($attribute) : $default;
    }

    /**
     * Returns the node name of the first node of the list.
     *
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function nodeName(): string
    {
        if (!$node = $this->getNode(0)) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        return $node instanceof \Dom\Element ? $node->localName : $node->nodeName;
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
        if (!$node = $this->getNode(0)) {
            if (null !== $default) {
                return $default;
            }

            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $text = $node->textContent;

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
            if (!$childNode instanceof \Dom\Text && !$childNode instanceof \Dom\CDATASection) {
                continue;
            }
            if (!$normalizeWhitespace) {
                return $childNode->data;
            }
            if ('' !== trim($childNode->data)) {
                return $this->normalizeWhitespace($childNode->data);
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
        if (!($node = $this->getNode(0)) instanceof \Dom\Element) {
            if (null !== $default) {
                return $default;
            }

            throw new \InvalidArgumentException('The current node list is empty.');
        }

        return $node->innerHTML;
    }

    /**
     * Returns the first node of the list as an HTML string, including the node itself.
     *
     * @throws \InvalidArgumentException When the current node is empty
     */
    public function outerHtml(): string
    {
        if (!($node = $this->getNode(0)) instanceof \Dom\Element) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        // \Dom\Element::$outerHTML was added in PHP 8.5, so the document serializes the node
        $document = $node->ownerDocument;

        if ($document instanceof \Dom\HTMLDocument) {
            return $document->saveHtml($node);
        }

        if ($document instanceof \Dom\XMLDocument) {
            return $document->saveXml($node);
        }

        throw new \LogicException(\sprintf('Unable to serialize a node of a "%s" document.', get_debug_type($document)));
    }

    /**
     * Evaluates an XPath expression.
     *
     * Since an XPath expression might evaluate to either a simple type or a \Dom\NodeList,
     * this method will return either an array of simple types or a new HtmlCrawler instance.
     *
     * @throws \LogicException when the crawler is uninitialized
     */
    public function evaluate(string $xpath): array|static
    {
        if (null === $this->document) {
            throw new \LogicException('Cannot evaluate the expression on an uninitialized crawler.');
        }

        $xpath = $this->prefixXPath($xpath);
        $domxpath = $this->createXPath();

        $data = [];
        foreach ($this->nodes as $node) {
            $data[] = $domxpath->evaluate($xpath, $node);
        }

        if (isset($data[0]) && $data[0] instanceof \Dom\NodeList) {
            $crawler = $this->createSubCrawler(null);

            foreach ($data as $nodeList) {
                if ($nodeList instanceof \Dom\NodeList) {
                    $crawler->addNodeList($nodeList);
                }
            }

            return $crawler;
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
                    $elements[] = $node->textContent;
                } elseif ('_name' === $attribute) {
                    $elements[] = $node instanceof \Dom\Element ? $node->localName : $node->nodeName;
                } else {
                    $elements[] = $node instanceof \Dom\Element ? $node->getAttribute($attribute) ?? '' : '';
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
     *
     * Unprefixed element name tests are matched against the HTML namespace the
     * parser puts elements in, so "//div" selects div elements as expected.
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
     * The selector is handled by the selector engine of the HTML parser, so
     * every selector that engine supports can be used.
     */
    public function filter(string $selector): static
    {
        $crawler = $this->createSubCrawler(null);

        foreach ($this->nodes as $node) {
            if ($node instanceof \Dom\ParentNode) {
                $crawler->addNodeList($node->querySelectorAll($selector));
            }
        }

        return $crawler;
    }

    /**
     * Selects links by name or alt value for clickable images.
     */
    public function selectLink(string $value): static
    {
        $value = ' '.$value.' ';

        return $this->select('a', static function (\Dom\Element $node) use ($value): bool {
            if (str_contains(' '.self::normalizeSpace($node->textContent).' ', $value)) {
                return true;
            }

            // \Dom\Element::$children was added in PHP 8.5
            foreach ($node->childNodes as $child) {
                if ($child instanceof \Dom\Element && 'img' === $child->localName && str_contains(' '.self::normalizeSpace($child->getAttribute('alt') ?? '').' ', $value)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Selects images by alt value.
     */
    public function selectImage(string $value): static
    {
        return $this->select('img', static fn (\Dom\Element $node): bool => str_contains(self::normalizeSpace($node->getAttribute('alt') ?? ''), $value));
    }

    /**
     * Selects a button by name or alt value for images.
     */
    public function selectButton(string $value): static
    {
        $paddedValue = ' '.$value.' ';

        return $this->select('input, button', static function (\Dom\Element $node) use ($value, $paddedValue): bool {
            if ($value === $node->getAttribute('id') || $value === $node->getAttribute('name')) {
                return true;
            }

            $hasValue = static fn (string $attribute): bool => str_contains(' '.self::normalizeSpace($node->getAttribute($attribute) ?? '').' ', $paddedValue);

            if ('button' === $node->localName) {
                return $hasValue('value') || str_contains(' '.self::normalizeSpace($node->textContent).' ', $paddedValue);
            }

            $type = strtolower($node->getAttribute('type') ?? '');

            if ((str_contains($type, 'submit') || str_contains($type, 'button')) && $hasValue('value')) {
                return true;
            }

            return str_contains($type, 'image') && $hasValue('alt');
        });
    }

    /**
     * Returns a Link object for the first node in the list.
     *
     * @throws \InvalidArgumentException If the current node list is empty or the node is not an element
     */
    public function link(string $method = 'get'): HtmlLink
    {
        return new HtmlLink($this->getFirstElement(), $this->baseHref, $method);
    }

    /**
     * @return HtmlLink[]
     *
     * @throws \InvalidArgumentException If the current node list contains non-elements
     */
    public function links(): array
    {
        $links = [];
        foreach ($this->nodes as $node) {
            $links[] = new HtmlLink($this->assertElement($node), $this->baseHref, 'get');
        }

        return $links;
    }

    /**
     * Returns an Image object for the first node in the list.
     *
     * @throws \InvalidArgumentException If the current node list is empty or the node is not an element
     */
    public function image(): HtmlImage
    {
        return new HtmlImage($this->getFirstElement(), $this->baseHref);
    }

    /**
     * @return HtmlImage[]
     *
     * @throws \InvalidArgumentException If the current node list contains non-elements
     */
    public function images(): array
    {
        $images = [];
        foreach ($this->nodes as $node) {
            $images[] = new HtmlImage($this->assertElement($node), $this->baseHref);
        }

        return $images;
    }

    /**
     * Returns a Form object for the first node in the list.
     *
     * @throws \InvalidArgumentException If the current node list is empty or the node is not an element
     */
    public function form(?array $values = null, ?string $method = null): HtmlForm
    {
        $form = new HtmlForm($this->getFirstElement(), $this->uri, $method, $this->baseHref);

        if (null !== $values) {
            $form->setValues($values);
        }

        return $form;
    }

    public function getNode(int $position): ?\Dom\Node
    {
        return $this->nodes[$position] ?? null;
    }

    public function count(): int
    {
        return \count($this->nodes);
    }

    /**
     * @return \ArrayIterator<int, \Dom\Node>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->nodes);
    }

    private function parseHtml(string $htmlContent, string $charset = 'UTF-8'): \Dom\HTMLDocument
    {
        // Elements are put in the HTML namespace on purpose: the selector engine
        // only applies HTML semantics, such as case-insensitive tag names and the
        // pseudo-classes that depend on the kind of an element, to elements that
        // are in it.
        try {
            return \Dom\HTMLDocument::createFromString($htmlContent, 0, $charset);
        } catch (\ValueError) {
            return \Dom\HTMLDocument::createFromString($htmlContent, 0);
        }
    }

    private function createXPath(): \Dom\XPath
    {
        $xpath = new \Dom\XPath($this->document);
        $xpath->registerNamespace(self::XPATH_PREFIX, self::HTML_NAMESPACE);

        return $xpath;
    }

    private function filterRelativeXPath(string $xpath): static
    {
        $crawler = $this->createSubCrawler(null);

        if (null === $this->document) {
            return $crawler;
        }

        $xpath = $this->prefixXPath($xpath);
        $domxpath = $this->createXPath();

        foreach ($this->nodes as $node) {
            $crawler->addNodeList($domxpath->query($xpath, $node));
        }

        return $crawler;
    }

    /**
     * Prefixes unprefixed element name tests with the HTML namespace prefix.
     *
     * XPath 1.0 resolves an unprefixed name test against no namespace at all,
     * so "//div" cannot match an element the parser put in the HTML namespace.
     * Names that are already prefixed, node type tests, function calls,
     * wildcards, attributes, variables and string literals are left alone.
     */
    private function prefixXPath(string $xpath): string
    {
        return preg_replace_callback(
            <<<'REGEXP'
                /
                    "[^"]*+" | '[^']*+'         (*SKIP)(*FAIL)  # skip string literals
                  | \$[\w.-]++                  (*SKIP)(*FAIL)  # skip variable references
                  | @[\w.:-]++                  (*SKIP)(*FAIL)  # skip attribute name tests
                  | (?<![\w.-])                                 # not in the middle of a name
                    (?<!(?<!:):)                                # not after a namespace separator, but "::" is an axis
                    (?P<name>[A-Za-z_][\w.-]*+)
                    (?![\w.-]*+ \s*+ [(:])                      # neither a function call nor a prefix itself
                /x
                REGEXP,
            static fn (array $m) => isset($m['name']) && '' !== $m['name'] ? self::XPATH_PREFIX.':'.$m['name'] : $m[0],
            $xpath
        ) ?? $xpath;
    }

    /**
     * @return list<\Dom\Node>
     */
    private function sibling(\Dom\Node $node, string $siblingDir = 'nextSibling'): array
    {
        $nodes = [];

        $currentNode = $this->getNode(0);
        do {
            if ($node !== $currentNode && $node instanceof \Dom\Element) {
                $nodes[] = $node;
            }
        } while ($node = $node->$siblingDir);

        return $nodes;
    }

    private function normalizeWhitespace(string $string): string
    {
        return trim(preg_replace("/(?:[ \n\r\t\x0C]{2,}+|[\n\r\t\x0C])/", ' ', $string), " \n\r\t\x0C");
    }

    /**
     * Collapses whitespace the way the XPath normalize-space() function does, so
     * that the selectors below match what the classic crawler matches. That set
     * of characters is narrower than the HTML5 one: it leaves the form feed out.
     */
    private static function normalizeSpace(string $string): string
    {
        return trim(preg_replace("/[ \n\r\t]++/", ' ', $string), " \n\r\t");
    }

    /**
     * Selects the nodes matching the selector, the current nodes included, that
     * the given predicate accepts.
     *
     * @param callable(\Dom\Element): bool $accept
     */
    private function select(string $selector, callable $accept): static
    {
        $crawler = $this->createSubCrawler(null);

        foreach ($this->nodes as $node) {
            if ($node instanceof \Dom\Element && $node->matches($selector) && $accept($node)) {
                $crawler->addNode($node);
            }

            if (!$node instanceof \Dom\ParentNode) {
                continue;
            }

            foreach ($node->querySelectorAll($selector) as $candidate) {
                if ($accept($candidate)) {
                    $crawler->addNode($candidate);
                }
            }
        }

        return $crawler;
    }

    /**
     * @throws \InvalidArgumentException If the current node list is empty or the node is not an element
     */
    private function getFirstElement(): \Dom\Element
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        return $this->assertElement($this->nodes[0]);
    }

    /**
     * @throws \InvalidArgumentException If the node is not an element
     */
    private function assertElement(\Dom\Node $node): \Dom\Element
    {
        if (!$node instanceof \Dom\Element) {
            throw new \InvalidArgumentException(\sprintf('The current node list should contain only Dom\Element instances, "%s" found.', get_debug_type($node)));
        }

        return $node;
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
     * Creates a crawler for some subnodes.
     *
     * @param \Dom\NodeList|\Dom\Node|\Dom\Node[]|string|null $nodes
     */
    private function createSubCrawler(\Dom\NodeList|\Dom\Node|array|string|null $nodes): static
    {
        $crawler = new static($nodes, $this->uri, $this->baseHref);
        $crawler->document = $this->document;

        return $crawler;
    }
}
