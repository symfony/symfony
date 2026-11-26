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
use Symfony\Bridge\TuiHtml\Content\NativeContentRegistry;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Bridge\TuiHtml\Widget\TextWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;

class NativeContentRegistryTest extends TestCase
{
    public function testPreparedLeafRetainsNativeIdentityAndCachesWidthDependentContent()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><app-widget></app-widget>');
        $element = $document->querySelector('app-widget');
        $widget = new class extends AbstractWidget {
            public int $preparations = 0;
            public int $renders = 0;

            public function beforeRender(): void
            {
                ++$this->preparations;
            }

            public function render(RenderContext $context): array
            {
                ++$this->renders;

                return array_fill(0, (int) ceil(12 / $context->getColumns()), str_repeat('X', min(12, $context->getColumns())));
            }
        };
        $registry = new NativeContentRegistry();
        $registry->register($element, $widget);
        $registry->prepare();
        $provider = $registry->resolve($element, ['color' => 'red']);
        self::assertSame(96.0, $provider->getIntrinsicSize()->preferredWidth);
        self::assertSame(48.0, $provider->layout(32, 100)->height);
        self::assertSame(48.0, $provider->layout(32, 100)->height);
        self::assertSame(1, $widget->preparations);
        self::assertSame(2, $widget->renders);
        self::assertSame($provider, $registry->resolve($element, ['color' => 'red']));
        $widget->invalidate();
        $this->expectException(\LogicException::class);
        $provider->layout(32, 100);
    }

    public function testFigletUsesActualTrimmedGlyphHeight()
    {
        $document = \Dom\HTMLDocument::createEmpty();
        $element = $document->createElement('p');
        $widget = new TextWidget('Hi');
        $registry = new NativeContentRegistry();
        $registry->register($element, $widget);
        $registry->prepare();
        $provider = $registry->resolve($element, ['font-family' => 'small']);
        self::assertSame(64.0, $provider->getIntrinsicSize()->height);
        self::assertSame(88.0, $provider->getIntrinsicSize()->preferredWidth);
        self::assertSame(64.0, $provider->layout(200, 200)->height);
        $widget->setText('_');
        $registry->prepare();
        self::assertSame(80.0, $registry->resolve($element, ['font-family' => 'small'])->getIntrinsicSize()->height);
        $widget->setText(' ');
        $registry->prepare();
        self::assertSame(0.0, $registry->resolve($element, ['font-family' => 'small'])->getIntrinsicSize()->height);
    }

    public function testMeasurementMutationIsRejectedInsteadOfProducingStaleContent()
    {
        $element = \Dom\HTMLDocument::createEmpty()->createElement('app-widget');
        $widget = new class extends AbstractWidget {
            public function render(RenderContext $context): array
            {
                $this->invalidate();

                return ['changed'];
            }
        };
        $registry = new NativeContentRegistry();
        $registry->register($element, $widget);
        $registry->prepare();
        $this->expectException(\LogicException::class);
        $registry->resolve($element, [])->getIntrinsicSize();
    }

    public function testDocumentWidgetPreparesBeforeCascadeAndComposesActualFigletHeight()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>app-native{display:block;font-family:small}p{margin:0}</style><app-native id="n"></app-native><p>AFTER</p>', new RenderingContext(400, 200));
        $native = new TextWidget('Hi');
        $registry = new NativeContentRegistry();
        $registry->register($session->getDocument()->getElementById('n'), $native);
        $widget = new DocumentWidget($session, new TerminalTextMeasurer(), $registry);
        $lines = array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(50, 10)));
        self::assertSame('AFTER', trim($lines[4]));
        self::assertStringContainsString('|', implode('', $lines));
        $first = $session->render();
        $widget->render(new RenderContext(50, 10));
        self::assertSame($first, $session->render());
        $native->setText('_');
        $lines = array_map(AnsiUtils::stripAnsiCodes(...), $widget->render(new RenderContext(50, 10)));
        self::assertSame('AFTER', trim($lines[5]));
    }

    public function testRegistrationOwnsWidgetAndNativeWrapperUntilReplacementOrUnregister()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><app-widget></app-widget>');
        $element = $document->querySelector('app-widget');
        $widget = new TextWidget('registered');
        $elementReference = \WeakReference::create($element);
        $widgetReference = \WeakReference::create($widget);
        $registry = new NativeContentRegistry();
        $registry->register($element, $widget);
        unset($element, $widget);
        gc_collect_cycles();
        self::assertNotNull($elementReference->get());
        self::assertNotNull($widgetReference->get());
        $registry->prepare();
        self::assertNotNull($registry->resolve($document->querySelector('app-widget'), []));
        $registry->register($document->querySelector('app-widget'), new TextWidget('replacement'));
        gc_collect_cycles();
        self::assertNull($widgetReference->get());
        $registry->prepare();
        $provider = $registry->resolve($document->querySelector('app-widget'), []);
        self::assertSame(88.0, $provider->getIntrinsicSize()->preferredWidth);
        $registry->unregister($document->querySelector('app-widget'));
        gc_collect_cycles();
        self::assertNull($elementReference->get());
        self::assertNull($registry->resolve($document->querySelector('app-widget'), []));
        $this->expectException(\LogicException::class);
        $provider->layout(80, 32);
    }
}
