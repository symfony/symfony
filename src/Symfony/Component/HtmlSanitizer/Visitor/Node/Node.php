<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Visitor\Node;

use Symfony\Component\HtmlSanitizer\TextSanitizer\StringSanitizer;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class Node implements NodeInterface
{
    // HTML5 elements which are self-closing
    private const VOID_ELEMENTS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'keygen' => true,
        'link' => true,
        'meta' => true,
        'param' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    private NodeInterface $parent;
    private string $tagName;
    private array $attributes = [];
    private array $children = [];

    public function __construct(NodeInterface $parent, string $tagName)
    {
        $this->parent = $parent;
        $this->tagName = $tagName;
    }

    public function getParent(): ?NodeInterface
    {
        return $this->parent;
    }

    public function getAttribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function setAttribute(string $name, ?string $value): void
    {
        // Always use only the first declaration (ease sanitization)
        if (!\array_key_exists($name, $this->attributes)) {
            $this->attributes[$name] = $value;
        }
    }

    public function addChild(NodeInterface $node): void
    {
        $this->children[] = $node;
    }

    public function render(): string
    {
        if (isset(self::VOID_ELEMENTS[$this->tagName])) {
            return '<'.$this->tagName.$this->renderAttributes().' />';
        }

        $rendered = '<'.$this->tagName.$this->renderAttributes().'>';
        foreach ($this->children as $child) {
            $rendered .= $child->render();
        }

        return $rendered.'</'.$this->tagName.'>';
    }

    private function renderAttributes(): string
    {
        $rendered = [];
        foreach ($this->attributes as $name => $value) {
            if (null === $value) {
                // Tag should be removed as a sanitizer found suspect data inside
                continue;
            }

            $attr = StringSanitizer::encodeHtmlEntities($name);

            if ('' !== $value) {
                // In quirks mode, IE8 does a poor job producing innerHTML values.
                // If JavaScript does:
                //      nodeA.innerHTML = nodeB.innerHTML;
                // and nodeB contains (or even if ` was encoded properly):
                //      <div attr="``foo=bar">
                // then IE8 will produce:
                //      <div attr=``foo=bar>
                // as the value of nodeB.innerHTML and assign it to nodeA.
                // IE8's HTML parser treats `` as a blank attribute value and foo=bar becomes a separate attribute.
                // Adding a space at the end of the attribute prevents this by forcing IE8 to put double
                // quotes around the attribute when computing nodeB.innerHTML.
                if (str_contains($value, '`')) {
                    $value .= ' ';
                }

                $attr .= '="'.StringSanitizer::encodeHtmlEntities($value).'"';
            }

            $rendered[] = $attr;
        }

        return $rendered ? ' '.implode(' ', $rendered) : '';
    }
}
