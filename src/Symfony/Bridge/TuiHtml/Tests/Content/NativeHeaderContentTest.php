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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Content\NativeContentAdapter;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Render\RenderContext;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Component\Tui\Render\RenderContext as NativeContext;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;

class NativeHeaderContentTest extends TestCase
{
    public function testHeaderRenderingDoesNotRenderOrDetachTheRealBody(): void
    {
        $body = new class extends AbstractWidget {
            public int $renders = 0;

            public function render(NativeContext $context): array
            {
                ++$this->renders;

                return ['BODY'];
            }
        };
        $widget = new CollapsibleWidget('Summary', $body, true, 'Description');
        $tui = new Tui(null, new VirtualTerminal(30, 8));
        $tui->add($widget);
        try {
            $header = $widget->renderHeader(new RenderContext(30, 8));
            self::assertSame(0, $body->renders);
            self::assertSame($widget, $body->getParent());
            self::assertNotNull($body->getContext());
            self::assertSame([...$header, 'BODY'], $widget->render(new RenderContext(30, 8)));
            self::assertSame(1, $body->renders);
        } finally {
            $tui->remove($widget);
            $tui->stop();
        }
    }

    public function testProjectionKeepsClosedSemanticBodyWithoutChangingDefaultMirrorOrLifecycle(): void
    {
        $view = new HtmlBuilder()->build('<details id="details"><summary>Summary</summary><p id="body-text">Body</p></details>');
        $details = $view->getById('details');
        $body = $details->getContent()->all()[0];
        $sheet = $view->getStyleSheet();
        $root = $view->getRoot();
        $media = new MediaContext();
        $sheet->prepare($root, $media);
        self::assertNull($sheet->getMirrorProjection()['document']->getElementById('body-text'));
        $enrich = static function (array $snapshot): void {};
        $first = $sheet->prepareProjection($root, $media, $enrich);
        $node = $first['document']->getElementById('body-text');
        self::assertNotNull($node);
        self::assertSame([], $details->all());
        self::assertNull($body->getContext());
        $details->expand();
        $opened = $sheet->prepareProjection($root, $media, $enrich);
        self::assertSame($node, $opened['document']->getElementById('body-text'));
        $details->collapse();
        $closed = $sheet->prepareProjection($root, $media, $enrich);
        self::assertSame($node, $closed['document']->getElementById('body-text'));
        $sheet->releaseProjection($root);
        $sheet->prepare($root, $media);
        self::assertNull($sheet->getMirrorProjection()['document']->getElementById('body-text'));
        self::assertSame($body, $details->getContent()->all()[0]);
    }

    public function testPreparedHeaderUsesTheActualWidgetAndExportsPortableContentOnly(): void
    {
        $widget = new CollapsibleWidget("S\x1b[31mummary\x1b[0m", new class extends AbstractWidget {
            public function render(NativeContext $context): array
            {
                throw new \LogicException('The HTML body must be laid out independently.');
            }
        }, true, 'Description');
        $adapter = new NativeContentAdapter(\WeakReference::create($widget), $widget->getRenderRevision(), [], new TerminalTextMeasurer(), new FontRegistry(), new MediaContext(), static fn (CollapsibleWidget $source, NativeContext|RenderContext $context): array => $source->renderHeader($context), 0.0);
        self::assertSame(0.0, $adapter->getIntrinsicSize()->minimumWidth);
        $content = $adapter->layout(80, 160);
        self::assertGreaterThan(0.0, $content->height);
        $text = '';
        foreach ($content->displayList as $operation) {
            if ('text' === $operation['type']) {
                self::assertStringNotContainsString("\x1b", $operation['text']);
                $text .= $operation['text'];
            }
        }
        self::assertStringContainsString('Summary', $text);
        self::assertStringNotContainsString('BODY', $text);
    }

    public function testPreparedPartDoesNotRetainItsNativeWidget(): void
    {
        $widget = new CollapsibleWidget('Summary', new class extends AbstractWidget {
            public function render(NativeContext $context): array
            {
                return [];
            }
        });
        $weak = \WeakReference::create($widget);
        $adapter = new NativeContentAdapter($weak, $widget->getRenderRevision(), [], new TerminalTextMeasurer(), new FontRegistry(), new MediaContext(), static fn (CollapsibleWidget $source, NativeContext|RenderContext $context): array => $source->renderHeader($context), 0.0);
        unset($widget);
        gc_collect_cycles();
        self::assertNull($weak->get());
        unset($adapter);
    }
}
