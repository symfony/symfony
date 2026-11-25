<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Event\FormSubmitEvent;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;

final class EmbeddedDocumentTest extends TestCase
{
    public function testNativeTabAndFilesystemCompletionStayInEmbeddedSession()
    {
        $directory = sys_get_temp_dir().'/html-renderer-embedded-'.bin2hex(random_bytes(8));
        mkdir($directory);
        touch($directory.'/report.pdf');
        $inner = '<input type="file" accept=".pdf"><button>Inside</button>';
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="'.htmlspecialchars($inner).'"></iframe><button>Outside</button>', new RenderingContext(400, 200));
        $widget = new DocumentWidget($session);
        $native = new InputWidget();
        $tui = new Tui(null, new VirtualTerminal(50, 16));
        $tui->add($widget);
        $tui->add($native);
        $tui->start();
        try {
            $tui->setFocus($widget);
            $frame = $session->getDocument()->querySelector('iframe');
            $child = $session->getEmbeddedSession($frame);
            self::assertSame($frame, $session->getFocusedElement());
            self::assertSame($child, $session->getFocusedSession());
            $input = $child->getDocument()->querySelector('input');
            $tui->handleInput($directory.'/re');
            self::assertSame('port.pdf', $child->getCompletion($input));
            $tui->handleInput("\t");
            self::assertSame($directory.'/report.pdf', $child->getValue($input));
            self::assertSame($input, $child->getFocusedElement());
            $tui->handleInput("\t");
            self::assertSame('button', $child->getFocusedElement()->localName);
            $tui->handleInput("\t");
            self::assertSame('button', $session->getFocusedElement()->localName);
            self::assertNull($child->getFocusedElement());
            $tui->handleInput("\t");
            self::assertSame($native, $tui->getFocusManager()->getFocus());
        } finally {
            $tui->stop();
            unlink($directory.'/report.pdf');
            rmdir($directory);
        }
    }

    public function testEmbeddedDocumentsReceiveDefaultPoliciesWithoutDomMutation()
    {
        $inner = '<progress value="75" max="100"></progress><select><option>Choice</option></select><input type="file"><style>p:condensed{color:red}</style><p>Density</p>';
        $session = new HtmlRenderer()->createSession('<div data-density="condensed"><iframe width="300" height="150" srcdoc="'.htmlspecialchars($inner).'"></iframe></div>', new RenderingContext(400, 200));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(50, 16);
        $widget->render($context);
        $child = $session->getEmbeddedSession($session->getDocument()->querySelector('iframe'));
        $document = $child->getDocument();
        $source = $document->saveHtml();
        $text = implode('', array_column($child->render()->getDisplayList()->getOperations(), 'text'));
        self::assertStringContainsString('75%', $text);
        self::assertStringContainsString('[ Choice', $text);
        self::assertStringContainsString('Path to a file', $text);
        self::assertSame('red', $child->render()->getComputedStyle($document->querySelector('p'))['color']);
        $before = $session->render();
        $widget->render($context);
        self::assertSame($before, $session->render());
        self::assertSame($source, $document->saveHtml());

        $session->setContext(new RenderingContext(400, 200, null, null, null, null, [], 'iframe{width:300px;height:150px}'));
        $widget->render($context);
        $text = implode('', array_column($child->render()->getDisplayList()->getOperations(), 'text'));
        self::assertStringNotContainsString('75%', $text);
        self::assertStringNotContainsString('[ Choice', $text);
        self::assertSame($source, $document->saveHtml());
    }

    public function testUnavailableFramesUseSharedAlternativeAndRecoverWithoutLoadingTwice()
    {
        $session = new HtmlRenderer()->createSession('<p>Page <iframe src="https://example.com" title="Example"></iframe>.</p>', new RenderingContext(400, 200));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(50, 16);
        $source = $session->getDocument()->saveHtml();
        $widget->render($context);
        $text = implode('', array_column($session->render()->getDisplayList()->getOperations(), 'text'));
        self::assertStringContainsString('[Example]', $text);
        self::assertSame($source, $session->getDocument()->saveHtml());
        $frame = $session->getDocument()->querySelector('iframe');
        $frame->setAttribute('srcdoc', '<p>Loaded</p>');
        $widget->render($context);
        self::assertNotNull($session->getEmbeddedSession($frame));
        $text = implode('', array_column($session->render()->getDisplayList()->getOperations(), 'text'));
        self::assertStringContainsString('Loaded', $text);
        self::assertStringNotContainsString('[Example]', $text);
        $frame->setAttribute('srcdoc', '');
        $widget->render($context);
        self::assertNotNull($session->getEmbeddedSession($frame));
        self::assertStringNotContainsString('[Example]', implode('', array_column($session->render()->getDisplayList()->getOperations(), 'text')));

        $frame->removeAttribute('srcdoc');
        $session->setContext(new RenderingContext(400, 200, null, null, null, null, [], 'iframe{display:inline-block;width:80px;height:60px}'));
        $widget->render($context);
        self::assertStringNotContainsString('[Example]', implode('', array_column($session->render()->getDisplayList()->getOperations(), 'text')));
    }

    public function testChildSubmissionsAreForwardedOnceWithoutOuterFormValues()
    {
        $inner = '<form action="/inner" method="post"><input name="child" value="A"><button>Send</button></form>';
        $session = new HtmlRenderer()->createSession('<form><input type="hidden" name="outer" value="B"><iframe srcdoc="'.htmlspecialchars($inner).'"></iframe></form>', new RenderingContext(400, 200));
        $widget = new DocumentWidget($session);
        $submissions = [];
        $widget->on(FormSubmitEvent::class, static function (FormSubmitEvent $event) use (&$submissions): void { $submissions[] = $event; });
        $widget->render(new RenderContext(50, 16));
        $widget->setFocused(true);
        $child = $session->getFocusedSession();
        $widget->handleInput('中');
        $widget->handleInput("\r");
        self::assertCount(1, $submissions);
        self::assertSame(['child' => 'A中'], $submissions[0]->getValues());
        self::assertSame('/inner', $submissions[0]->getAction());
        self::assertSame('post', $submissions[0]->getMethod());
        $child->on('submit', static fn ($event) => $event->preventDefault());
        $widget->handleInput("\r");
        self::assertCount(1, $submissions);
    }

    public function testReturningFromNativeFocusRestoresTheActualChildControlOnce()
    {
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="&lt;input&gt;"></iframe>', new RenderingContext(400, 200));
        $widget = new DocumentWidget($session);
        $native = new InputWidget();
        $tui = new Tui(null, new VirtualTerminal(50, 16), new Keybindings(['focus_previous' => ['shift+tab']]));
        $tui->add($widget);
        $tui->add($native);
        $tui->start();
        try {
            $tui->setFocus($widget);
            $child = $session->getFocusedSession();
            $input = $child->getFocusedElement();
            $events = [];
            $child->on('focus', static function () use (&$events): void { $events[] = 'focus'; });
            $child->on('blur', static function () use (&$events): void { $events[] = 'blur'; });
            $tui->handleInput('x');
            $tui->handleInput("\t");
            self::assertSame($native, $tui->getFocusManager()->getFocus());
            $tui->handleInput("\x1b[Z");
            self::assertSame($widget, $tui->getFocusManager()->getFocus());
            self::assertSame($input, $child->getFocusedElement());
            self::assertSame($child, $session->getFocusedSession());
            $tui->handleInput('y');
            self::assertSame('xy', $child->getValue($input));
            self::assertSame(['blur', 'focus'], $events);
        } finally {
            $tui->stop();
        }
    }

    public function testBlurredSourceReplacementDoesNotRetainOrRestoreTheOldChild()
    {
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="&lt;input value=Old&gt;"></iframe>', new RenderingContext(400, 200));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(50, 16);
        $widget->render($context);
        $widget->setFocused(true);
        $old = \WeakReference::create($session->getFocusedSession());
        $widget->setFocused(false);
        $frame = $session->getDocument()->querySelector('iframe');
        $frame->setAttribute('srcdoc', '<input value="New">');
        $widget->render($context);
        gc_collect_cycles();
        self::assertNull($old->get());
        $widget->setFocused(true);
        $child = $session->getFocusedSession();
        self::assertNotSame($session, $child);
        self::assertSame('New', $child->getValue($child->getFocusedElement()));
        $widget->handleInput('y');
        self::assertSame('Newy', $child->getValue($child->getFocusedElement()));
    }

    public function testNestedFocusPathRestoresTheLeafCaretWithoutDuplicateEvents()
    {
        $source = '<input value="AB">';
        for ($level = 0; $level < 3; ++$level) {
            $source = '<iframe srcdoc="'.htmlspecialchars($source).'"></iframe>';
        }
        $session = new HtmlRenderer()->createSession($source, new RenderingContext(400, 200));
        $widget = new DocumentWidget($session);
        $widget->render(new RenderContext(50, 16));
        $widget->setFocused(true);
        $leaf = $session->getFocusedSession();
        $input = $leaf->getFocusedElement();
        $leaf->setTextSelection($input, 1, 1);
        $events = [];
        $leaf->on('focus', static function () use (&$events): void { $events[] = 'focus'; });
        $leaf->on('blur', static function () use (&$events): void { $events[] = 'blur'; });
        $widget->setFocused(false);
        $widget->setFocused(true);
        self::assertSame($leaf, $session->getFocusedSession());
        $widget->handleInput('X');
        self::assertSame('AXB', $leaf->getValue($input));
        self::assertSame(['blur', 'focus'], $events);
    }
}
