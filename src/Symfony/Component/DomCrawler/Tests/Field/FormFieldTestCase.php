<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests\Field;

use PHPUnit\Framework\TestCase;

class FormFieldTestCase extends TestCase
{
    protected function createNode($tag, $value, $attributes = [])
    {
        $document = new \DOMDocument();
        $node = $document->createElement($tag, $value);

        foreach ($attributes as $name => $value) {
            $node->setAttribute($name, $value);
        }

        return $node;
    }

    /**
     * The native parser has no way to build an element out of a document, so the
     * node is parsed instead of created, then given its attributes. The closing
     * tag is left out because the parser rejects one on a void element such as
     * input, and closes every other element on its own.
     */
    protected function createHtmlNode(string $tag, string $value = '', array $attributes = []): \Dom\Element
    {
        $document = \Dom\HTMLDocument::createFromString(\sprintf('<!doctype html><body><%s>%s', $tag, $value), 0);
        $node = $document->querySelector($tag);

        foreach ($attributes as $name => $attributeValue) {
            $node->setAttribute($name, $attributeValue);
        }

        return $node;
    }
}
