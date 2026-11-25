<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class PrivateStyleBoundaryTest extends TestCase
{
    public function testVisualDecorationPropagatesWithoutChangingComputedInheritance()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><p style="color:red;font-size:20px;text-decoration:underline wavy currentColor .1em"><span style="color:blue;font-size:10px;text-decoration:none">Text</span></p>', new RenderingContext(400, 200));
        $span = $result->getDocument()->querySelector('span');
        self::assertSame('none', $result->getComputedStyle($span)['text-decoration-line']);
        $operation = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']))[0];
        self::assertSame('blue', $operation['color']);
        self::assertSame('underline', $operation['text-decoration-line']);
        self::assertSame('red', $operation['text-decoration-color']);
        self::assertSame(2.0, $operation['text-decoration-thickness']);
    }

    public function testGeneratedPseudoPropertiesCannotSpoofLayoutMetadata()
    {
        $renderer = new HtmlRenderer();
        $html = '<!DOCTYPE html><style>span::before{content:"A";display:inline-block;%s}</style><span style="display:inline-block"></span>B';
        $expected = $renderer->render(\sprintf($html, ''), new RenderingContext(400, 200))->getDisplayList()->getOperations();
        $actual = $renderer->render(\sprintf($html, '-renderer-relative-y:1000;-renderer-baseline:1000;-renderer-baseline-shift:1000'), new RenderingContext(400, 200))->getDisplayList()->getOperations();
        self::assertSame($expected, $actual);
    }

    public function testAuthorPropertiesCannotSpoofLayoutMetadata()
    {
        $renderer = new HtmlRenderer();
        $html = '<!DOCTYPE html><span style="display:inline-block"><span style="display:inline-block;%s">A</span></span>B';
        $expected = $renderer->render(\sprintf($html, ''), new RenderingContext(400, 200))->getDisplayList()->getOperations();
        $actual = $renderer->render(\sprintf($html, '-renderer-relative-y:1000;-renderer-baseline:1000;-renderer-baseline-shift:1000'), new RenderingContext(400, 200))->getDisplayList()->getOperations();
        self::assertSame($expected, $actual);
    }
}
