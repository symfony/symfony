<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class FloatInlineFlowTest extends TestCase
{
    public function testShortTextUsesTheWholeIntervalBetweenFloats()
    {
        $operations = $this->renderText('Ada short');

        self::assertCount(1, $operations);
        self::assertSame('Ada short', $operations[0]['text']);
        self::assertSame(20.0, $operations[0]['x']);
        self::assertSame(0.0, $operations[0]['y']);
        self::assertSame(45.0, $operations[0]['width']);
    }

    public function testFragmentedTextStartsBesideFloatsAndExpandsWhenTheyEnd()
    {
        $operations = $this->renderText('Ada Lovelace wrote the first program, for an engine that was never built.');

        self::assertSame('Ada ', $operations[0]['text']);
        self::assertSame(20.0, $operations[0]['x']);
        self::assertSame(0.0, $operations[0]['y']);
        foreach ($operations as $operation) {
            if ($operation['y'] < 20.0) {
                self::assertGreaterThanOrEqual(20.0, $operation['x']);
            }
            if ($operation['y'] < 40.0) {
                self::assertLessThanOrEqual(90.0, $operation['x'] + $operation['width']);
            }
        }
        self::assertNotEmpty(array_filter($operations, static fn (array $operation): bool => $operation['y'] >= 20.0 && 0.0 === $operation['x']));
    }

    public function testFloatIntervalsRetainFractionalCoordinates()
    {
        $operations = $this->renderText('Ada short', 119.5, 20.25, 30.125);

        self::assertCount(1, $operations);
        self::assertSame(20.25, $operations[0]['x']);
        self::assertSame(45.0, $operations[0]['width']);
    }

    public function testSiblingInlineElementsShareTheFloatInterval()
    {
        $operations = $this->renderText('<span>Ada </span><em>short</em>');

        self::assertCount(2, $operations);
        self::assertSame([0.0, 0.0], array_column($operations, 'y'));
        self::assertSame([20.0, 40.0], array_column($operations, 'x'));
        self::assertSame('Ada short', implode('', array_column($operations, 'text')));
    }

    private function renderText(string $text, float $width = 120.0, float $left = 20.0, float $right = 30.0): array
    {
        $html = '<style>body,p{margin:0}body{font-size:10px;line-height:10px}.left{float:left;width:'.$left.'px;height:20px}.right{float:right;width:'.$right.'px;height:40px}p{overflow-wrap:anywhere}</style><div class="right"></div><div class="left"></div><p>'.$text.'</p>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext($width, 100));

        return array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
    }
}
