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
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;

final class DocumentCompletionTest extends TestCase
{
    public function testNativeTabAcceptsDatalistBeforeTraversingDocumentAndHostFocus()
    {
        $session = new HtmlRenderer()->createSession('<input list="words"><button>Next</button><datalist id="words"><option>PHP</option></datalist>', new RenderingContext(400, 100));
        $widget = new DocumentWidget($session);
        $native = new InputWidget();
        $tui = new Tui(null, new VirtualTerminal(50, 8));
        $tui->add($widget);
        $tui->add($native);
        $tui->start();
        try {
            $tui->setFocus($widget);
            $input = $session->getDocument()->querySelector('input');
            $tui->handleInput('p');
            $tui->handleInput("\t");
            self::assertSame('pHP', $session->getValue($input));
            self::assertSame($input, $session->getFocusedElement());
            self::assertSame($widget, $tui->getFocusManager()->getFocus());
            $tui->handleInput("\t");
            self::assertSame('button', $session->getFocusedElement()->localName);
            $tui->handleInput("\t");
            self::assertSame($native, $tui->getFocusManager()->getFocus());
        } finally {
            $tui->stop();
        }
    }

    public function testCanceledTabDoesNotTransferNativeFocus()
    {
        $session = new HtmlRenderer()->createSession('<input>', new RenderingContext(400, 100));
        $widget = new DocumentWidget($session);
        $tui = new Tui(null, new VirtualTerminal(50, 8));
        $tui->add($widget);
        $tui->add(new InputWidget());
        $tui->start();
        try {
            $tui->setFocus($widget);
            $events = 0;
            $session->on('keydown', static function ($event) use (&$events) {
                ++$events;
                $event->preventDefault();
            });
            $tui->handleInput("\t");
            self::assertSame(1, $events);
            self::assertSame($widget, $tui->getFocusManager()->getFocus());
        } finally {
            $tui->stop();
        }
    }

    public function testTerminalPathCompletionRetainsAcceptAndMultipleSemantics()
    {
        $directory = sys_get_temp_dir().'/html-renderer-paths-'.bin2hex(random_bytes(8));
        mkdir($directory);
        touch($directory.'/report.pdf');
        touch($directory.'/report.txt');
        $session = new HtmlRenderer()->createSession('<input type="file" accept=".pdf" multiple><button>Next</button>', new RenderingContext(400, 100));
        $widget = new DocumentWidget($session);
        $tui = new Tui(null, new VirtualTerminal(50, 8));
        $tui->add($widget);
        $tui->start();
        try {
            $tui->setFocus($widget);
            $input = $session->getDocument()->querySelector('input');
            $source = $session->getDocument()->saveHtml();
            $tui->handleInput('first.pdf, '.$directory.'/re');
            self::assertSame('port.pdf', $session->getCompletion($input));
            $tui->handleInput("\t");
            self::assertSame('first.pdf, '.$directory.'/report.pdf', $session->getValue($input));
            self::assertSame($input, $session->getFocusedElement());
            self::assertSame($source, $session->getDocument()->saveHtml());

            $headless = new HtmlRenderer()->createSession('<input type="file">', new RenderingContext(400, 100));
            $headlessInput = $headless->getDocument()->querySelector('input');
            $headless->focus($headlessInput);
            $headlessWidget = new DocumentWidget($headless);
            $headlessWidget->handleInput($directory.'/re');
            $headlessWidget->handleInput("\t");
            self::assertSame($directory.'/report.pdf', $headless->getValue($headlessInput));

            $bounded = new HtmlRenderer(\strlen($directory) + 30)->createSession('<input type="file">', new RenderingContext(400, 100));
            $boundedInput = $bounded->getDocument()->querySelector('input');
            $bounded->focus($boundedInput);
            new DocumentWidget($bounded)->handleInput($directory.'/re');
            self::assertSame($directory.'/re', $bounded->getValue($boundedInput));
            self::assertNull($bounded->getCompletion($boundedInput));
        } finally {
            $tui->stop();
            unlink($directory.'/report.pdf');
            unlink($directory.'/report.txt');
            rmdir($directory);
        }
    }
}
