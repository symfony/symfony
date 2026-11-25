<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Content;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingResult;

class OverflowContentTest extends TestCase
{
    public function testLocalContentCanOverflowItsFlowBoundsWithoutRemovingCssClipping(): void
    {
        foreach ([true, false] as $clip) {
            foreach (['visible', 'hidden'] as $overflow) {
                $content = new ContentLayout(10.0, new DisplayList([['type' => 'rectangle', 'x' => 0.0, 'y' => 0.0, 'width' => 20.0, 'height' => 30.0, 'color' => '#ff0000']]), $clip);
                $document = \Dom\HTMLDocument::createFromString('<!doctype html><div></div>');
                $element = $document->querySelector('div');
                $fragment = new Fragment($element, new Rect(0.0, 0.0, 20.0, 10.0), ['overflow' => $overflow], null, [], null, null, [], null, null, null, null, null, null, $content);
                $operations = (new Painter(new MonospaceTextMeasurer()))->paint($fragment)->getOperations();
                $clips = array_values(array_filter($operations, static fn (array $operation): bool => 'push-clip' === $operation['type']));
                self::assertCount(($clip ? 1 : 0) + ('hidden' === $overflow ? 1 : 0), $clips);
                self::assertSame(10.0, $fragment->bounds->height);
                $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, new DisplayList($operations));
                self::assertSame(!$clip && 'visible' === $overflow, $result->isContentPointVisible($element, 5.0, 20.0));
            }
        }
    }
}
