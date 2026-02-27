<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Visitor;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\TextSanitizer\StringSanitizer;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;
use Symfony\Component\HtmlSanitizer\Visitor\Model\Cursor;
use Symfony\Component\HtmlSanitizer\Visitor\Node\BlockedNode;
use Symfony\Component\HtmlSanitizer\Visitor\Node\DocumentNode;
use Symfony\Component\HtmlSanitizer\Visitor\Node\Node;
use Symfony\Component\HtmlSanitizer\Visitor\Node\NodeInterface;
use Symfony\Component\HtmlSanitizer\Visitor\Node\TextNode;

/**
 * Iterates over the parsed DOM tree to build the sanitized tree.
 *
 * The DomVisitor iterates over the parsed DOM tree, visits its nodes and build
 * a sanitized tree with their attributes and content.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 *
 * @internal
 */
final class DomVisitor
{
    private HtmlSanitizerAction $defaultAction = HtmlSanitizerAction::Drop;

    /**
     * Registry of attributes to forcefully set on nodes, index by element and attribute.
     *
     * @var array<string, array<string, string>>
     */
    private array $forcedAttributes;

    /**
     * Registry of attributes sanitizers indexed by element name and attribute name for
     * faster sanitization.
     *
     * @var array<string, array<string, list<AttributeSanitizerInterface>>>
     */
    private array $attributeSanitizers = [];

    /**
     * @param array<string, HtmlSanitizerAction|array<string, bool>> $elementsConfig Registry of allowed/blocked elements:
     *                                                                               * If an element is present as a key and contains an array, the element should be allowed
     *                                                                               and the array is the list of allowed attributes.
     *                                                                               * If an element is present as a key and contains an HtmlSanitizerAction, that action applies.
     *                                                                               * If an element is not present as a key, the default action applies.
     */
    public function __construct(
        private HtmlSanitizerConfig $config,
        private array $elementsConfig,
    ) {
        $this->forcedAttributes = $config->getForcedAttributes();

        foreach ($config->getAttributeSanitizers() as $attributeSanitizer) {
            foreach ($attributeSanitizer->getSupportedElements() ?? ['*'] as $element) {
                foreach ($attributeSanitizer->getSupportedAttributes() ?? ['*'] as $attribute) {
                    $this->attributeSanitizers[$element][$attribute][] = $attributeSanitizer;
                }
            }
        }

        $this->defaultAction = $config->getDefaultAction();
    }

    public function visit(\Dom\Node|\DOMNode $domNode): ?NodeInterface
    {
        $cursor = new Cursor(new DocumentNode());
        $this->visitChildren($domNode, $cursor);

        return $cursor->node;
    }

    private function visitNode(\Dom\Node|\DOMNode $domNode, Cursor $cursor): void
    {
        $nodeName = StringSanitizer::htmlLower($domNode->nodeName);

        // Visit recursively if the node was not dropped
        if ($this->enterNode($nodeName, $domNode, $cursor)) {
            $this->visitChildren($domNode, $cursor);
            $cursor->node = $cursor->node->getParent();
        }
    }

    private function enterNode(string $domNodeName, \Dom\Node|\DOMNode $domNode, Cursor $cursor): bool
    {
        if (!\array_key_exists($domNodeName, $this->elementsConfig)) {
            $action = $this->defaultAction;
            $allowedAttributes = [];
        } else {
            if (\is_array($this->elementsConfig[$domNodeName])) {
                $action = HtmlSanitizerAction::Allow;
                $allowedAttributes = $this->elementsConfig[$domNodeName];
            } else {
                $action = $this->elementsConfig[$domNodeName];
                $allowedAttributes = [];
            }
        }

        if (HtmlSanitizerAction::Drop === $action) {
            return false;
        }

        // Element should be blocked, retaining its children
        if (HtmlSanitizerAction::Block === $action) {
            $node = new BlockedNode($cursor->node);

            $cursor->node->addChild($node);
            $cursor->node = $node;

            return true;
        }

        // Otherwise create the node
        $node = new Node($cursor->node, $domNodeName);
        $this->setAttributes($domNodeName, $domNode, $node, $allowedAttributes);

        // Force configured attributes
        foreach ($this->forcedAttributes[$domNodeName] ?? [] as $attribute => $value) {
            $node->setAttribute($attribute, $value, true);
        }

        $cursor->node->addChild($node);
        $cursor->node = $node;

        return true;
    }

    private function visitChildren(\Dom\Node|\DOMNode $domNode, Cursor $cursor): void
    {
        /** @var \Dom\Node|\DOMNode $child */
        foreach ($domNode->childNodes ?? [] as $child) {
            if ('#text' === $child->nodeName) {
                // Add text directly for performance
                $cursor->node->addChild(new TextNode($cursor->node, $child instanceof \Dom\Node ? ($child->textContent ?? '') : $child->nodeValue));
            } elseif (!$child instanceof \Dom\Text && !$child instanceof \Dom\ProcessingInstruction && !$child instanceof \DOMText && !$child instanceof \DOMProcessingInstruction) {
                // Otherwise continue the visit recursively
                // Ignore comments for security reasons (interpreted differently by browsers)
                // Ignore processing instructions (treated as comments)
                $this->visitNode($child, $cursor);
            }
        }
    }

    /**
     * Set attributes from a DOM node to a sanitized node.
     */
    private function setAttributes(string $domNodeName, \Dom\Node|\DOMNode $domNode, Node $node, array $allowedAttributes = []): void
    {
        /** @var iterable<\Dom\Attr|\DOMAttr> $domAttributes */
        if (!$domAttributes = $domNode->attributes?->getIterator()) {
            return;
        }

        foreach ($domAttributes as $attribute) {
            $name = StringSanitizer::htmlLower($attribute->name);

            if (isset($allowedAttributes[$name])) {
                $value = $attribute->value;

                // Sanitize the attribute value if there are attribute sanitizers for it
                $attributeSanitizers = array_merge(
                    $this->attributeSanitizers[$domNodeName][$name] ?? [],
                    $this->attributeSanitizers['*'][$name] ?? [],
                    $this->attributeSanitizers[$domNodeName]['*'] ?? [],
                );

                foreach ($attributeSanitizers as $sanitizer) {
                    if (null === $sanitizedValue = $sanitizer->sanitizeAttribute($domNodeName, $name, $value, $this->config)) {
                        continue 2;
                    }
                    $value = $sanitizedValue;
                }

                $node->setAttribute($name, $value);
            }
        }
    }
}
