<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Content;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Content\NativeContentAdapter;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Widget\ImageWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;

#[RequiresPhpExtension('gd')]
final class ImageContentAdapterTest extends TestCase
{
    public function testIntrinsicMeasurementDoesNotPaintNaturalRasterCells()
    {
        $widget = new ImageWidget(file_get_contents(__DIR__.'/../Fixtures/Compat/quadrants.png'), '', 200, 100);
        $adapter = new NativeContentAdapter(\WeakReference::create($widget), $widget->getRenderRevision(), [], new TerminalTextMeasurer(), new FontRegistry(), new MediaContext());
        $size = $adapter->getIntrinsicSize();
        self::assertSame(1600.0, $size->preferredWidth);
        self::assertSame(1600.0, $size->height);
        self::assertSame([], new \ReflectionProperty(ImageWidget::class, 'rendered')->getValue($widget));
        self::assertSame($size, $adapter->getIntrinsicSize());
        self::assertSame([], new \ReflectionProperty(ImageWidget::class, 'rendered')->getValue($widget));
    }

    public function testRawAspectRatioSurvivesTerminalCellRoundingAndRepeatedAllocation()
    {
        $data = file_get_contents(__DIR__.'/../Fixtures/Compat/quadrants.png');
        $image = imagescale(imagecreatefromstring($data), 8, 8);
        ob_start();
        imagepng($image);
        $widget = new ImageWidget(ob_get_clean());
        $revision = $widget->getRenderRevision();
        $adapter = new NativeContentAdapter(\WeakReference::create($widget), $revision, [], new TerminalTextMeasurer(), new FontRegistry(), new MediaContext());
        $size = $adapter->getIntrinsicSize();
        self::assertSame(1.0, $size->aspectRatio, 'The source is square even when its natural terminal height rounds to a full row.');
        self::assertSame(8.0, $size->preferredWidth);
        self::assertSame(16.0, $size->height);
        $first = $adapter->layout(16, 16);
        self::assertSame(16.0, $first->height);
        self::assertSame(32.0, $adapter->layout(32, 32)->height);
        self::assertSame($first->displayList->getOperations(), $adapter->layout(16, 16)->displayList->getOperations());
        self::assertSame($size, $adapter->getIntrinsicSize());
        self::assertSame($revision, $widget->getRenderRevision());
    }

    public function testConstructorDimensionsRemainNaturalUntilCssOverridesAnAxis()
    {
        $data = file_get_contents(__DIR__.'/../Fixtures/Compat/quadrants.png');
        $widget = new ImageWidget($data, '', 4, 1);
        $metrics = new TerminalTextMeasurer();
        $adapter = new NativeContentAdapter(\WeakReference::create($widget), $widget->getRenderRevision(), [], $metrics, new FontRegistry(), new MediaContext());
        $engine = new \Symfony\Component\HtmlRenderer\Layout\LayoutEngine($metrics, new \Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics($metrics));
        foreach ([[], ['width' => '32px'], []] as $index => $style) {
            $box = new \Symfony\Component\HtmlRenderer\Layout\Box(null, 'inline-block', $style, [], null, null, null, null, null, null, null, $adapter);
            $fragment = $engine->layout($box, 200, 100);
            self::assertSame([16.0, 32.0, 16.0][$index], $fragment->bounds->height);
            self::assertSame(32.0, $fragment->bounds->width);
        }
    }

    public function testSvgMetadataRefreshesItsReaderWithoutRasterization()
    {
        $initial = \Symfony\Bridge\TuiHtml\Style\VectorImage::fromString('<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8"><rect width="8" height="8"/></svg>');
        $updated = \Symfony\Bridge\TuiHtml\Style\VectorImage::fromString('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="32"><rect width="24" height="32"/></svg>');
        $reads = 0;
        $widget = new \Symfony\Bridge\TuiHtml\Widget\SvgWidget($initial, '', static function () use (&$reads, $updated) {
            ++$reads;

            return $updated;
        });
        $adapter = new NativeContentAdapter(\WeakReference::create($widget), $widget->getRenderRevision(), [], new TerminalTextMeasurer(), new FontRegistry(), new MediaContext());
        $size = $adapter->getIntrinsicSize();
        self::assertSame([24.0, 32.0, 0.75], [$size->preferredWidth, $size->height, $size->aspectRatio]);
        self::assertSame(1, $reads);
        self::assertSame([], new \ReflectionProperty($widget::class, 'images')->getValue($widget));
        $widget->invalidateSource();
        $next = new NativeContentAdapter(\WeakReference::create($widget), $widget->getRenderRevision(), [], new TerminalTextMeasurer(), new FontRegistry(), new MediaContext());
        self::assertSame(24.0, $next->getIntrinsicSize()->preferredWidth);
        self::assertSame(2, $reads);
    }

    public function testInvalidImageRetainsItsTextAlternativeMeasurement()
    {
        $widget = new ImageWidget('invalid image', 'missing');
        $adapter = new NativeContentAdapter(\WeakReference::create($widget), $widget->getRenderRevision(), [], new TerminalTextMeasurer(), new FontRegistry(), new MediaContext());
        $size = $adapter->getIntrinsicSize();
        self::assertSame([72.0, 16.0, null], [$size->preferredWidth, $size->height, $size->aspectRatio]);
    }

    public function testSvgProvidersAlsoExposeTheirRatioBeforeCellRounding()
    {
        $vector = \Symfony\Bridge\TuiHtml\Style\VectorImage::fromString('<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8"><rect width="8" height="8" fill="red"/></svg>');
        self::assertNotNull($vector);
        $widget = new \Symfony\Bridge\TuiHtml\Widget\SvgWidget($vector);
        $adapter = new NativeContentAdapter(\WeakReference::create($widget), $widget->getRenderRevision(), [], new TerminalTextMeasurer(), new FontRegistry(), new MediaContext());
        $size = $adapter->getIntrinsicSize();
        self::assertSame([8.0, 16.0, 1.0], [$size->preferredWidth, $size->height, $size->aspectRatio]);
        self::assertSame(16.0, $adapter->layout(16, 16)->height);
    }
}
