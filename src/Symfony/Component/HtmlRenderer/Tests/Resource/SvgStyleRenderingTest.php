<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Resource;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\DeclarationParser;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class SvgStyleRenderingTest extends TestCase
{
    public function testSerializedSvgKeepsPresentationFontAndResolvedKeywordDefaults()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>.reset{fill:initial;opacity:initial;visibility:initial}.inherited{fill:unset}</style><svg width="40" height="10" color="red"><g fill="green"><rect class="inherited" width="10" height="10"/><rect class="reset" x="10" width="10" height="10" fill="red" opacity=".2" visibility="hidden"/></g><text font-size="7">A</text></svg>');
        $before = $document->saveHtml();
        $result = (new HtmlRenderer())->render($document, new RenderingContext(100, 100));
        $operation = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'svg' === $op['type']))[0];
        $serialized = \Dom\XMLDocument::createFromString($operation['data']);
        $values = static function (\Dom\Element $element): array {
            $properties = [];
            foreach (DeclarationParser::parse($element->getAttribute('style') ?? '') as $declaration) {
                $properties[$declaration->property] = $declaration->value;
            }

            return $properties;
        };
        self::assertSame('7px', $values($serialized->querySelector('text'))['font-size']);
        self::assertSame('red', $values($serialized->documentElement)['color']);
        self::assertSame('green', $values($serialized->querySelector('.inherited'))['fill']);
        self::assertSame('black', $values($serialized->querySelector('.reset'))['fill']);
        self::assertSame('1', $values($serialized->querySelector('.reset'))['opacity']);
        self::assertSame('visible', $values($serialized->querySelector('.reset'))['visibility']);
        self::assertSame($before, $document->saveHtml());
    }

    public function testPresentationValueCannotBecomeAnotherSerializedDeclaration()
    {
        $result = (new HtmlRenderer())->render('<svg width="10" height="10"><rect width="10" height="10" fill="red;color:blue!important"/></svg>', new RenderingContext(100, 100));
        $operation = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'svg' === $op['type']))[0];
        $serialized = \Dom\XMLDocument::createFromString($operation['data']);
        $declarations = DeclarationParser::parse($serialized->querySelector('rect')->getAttribute('style'));
        self::assertNotContains('blue', array_map(static fn ($declaration): string => $declaration->value, $declarations));
        self::assertNotContains(true, array_map(static fn ($declaration): bool => $declaration->important, $declarations));
    }
}
