<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class InterestForTest extends TestCase
{
    private const HTML = '<!DOCTYPE html><button id="save" interestfor="tip">Save</button><input id="field"><div id="tip" popover="hint" style="left:200px;top:0;width:100px;height:40px;margin:0"><button id="inside">Read</button></div><div id="menu" popover>Menu</div>';

    public function testPopoverKeptByFocusCanCloseAfterItsInvokerIsRemoved()
    {
        $session = new HtmlRenderer()->createSession(self::HTML, new RenderingContext(500, 200));
        $document = $session->getDocument();
        $tip = $document->getElementById('tip');
        $session->focus($document->getElementById('save'));
        $session->focus($document->getElementById('inside'));
        $document->getElementById('save')->remove();
        self::assertNotEmpty($session->render()->getBounds($tip));
        $session->focus($document->getElementById('field'));
        self::assertSame([], $session->render()->getBounds($tip));
    }

    public function testBeforeToggleAdoptionCannotRegisterAForeignPopover()
    {
        $session = new HtmlRenderer()->createSession(self::HTML, new RenderingContext(500, 200));
        $document = $session->getDocument();
        $tip = $document->getElementById('tip');
        $other = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $session->on('beforetoggle', static function ($event) use ($other, $tip) {
            if ('open' === $event->data['newState']) {
                $other->querySelector('main')->appendChild($other->adoptNode($tip));
            }
        });
        $session->focus($document->getElementById('save'));
        $session->render();
        self::assertSame([], (new \ReflectionProperty($session, 'topLayers'))->getValue($session));
        self::assertTrue($tip->isConnected);
        self::assertSame($other, $tip->ownerDocument);
    }

    public function testAdoptionIntoAnotherConnectedDocumentClearsFocusAndInterest()
    {
        $session = new HtmlRenderer()->createSession(self::HTML, new RenderingContext(500, 200));
        $document = $session->getDocument();
        $invoker = $document->getElementById('save');
        $tip = $document->getElementById('tip');
        $session->setState($invoker, 'custom');
        $session->focus($invoker);
        self::assertNotEmpty($session->render()->getBounds($tip));
        $other = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $other->querySelector('main')->appendChild($other->adoptNode($invoker));
        self::assertTrue($invoker->isConnected);
        self::assertNull($session->getFocusedElement());
        self::assertSame([], $session->render()->getBounds($tip));
        $property = new \ReflectionProperty($session, 'states');
        self::assertFalse(isset($property->getValue($session)[$invoker]));
    }

    public function testFocusKeepsInterestAcrossInvokerAndPopoverWithoutChangingAttributes()
    {
        $session = new HtmlRenderer()->createSession(self::HTML, new RenderingContext(500, 200));
        $document = $session->getDocument();
        $source = $document->saveHtml();
        $tip = $document->getElementById('tip');
        $session->focus($document->getElementById('save'));
        self::assertNotEmpty($session->render()->getBounds($tip));
        $session->focus($document->getElementById('inside'));
        self::assertNotEmpty($session->render()->getBounds($tip));
        $session->focus($document->getElementById('field'));
        self::assertSame([], $session->render()->getBounds($tip));
        self::assertSame($source, $document->saveHtml());
    }

    public function testHoverTransfersToPopoverAndClosesWhenItLeavesBoth()
    {
        $session = new HtmlRenderer()->createSession(self::HTML, new RenderingContext(500, 200));
        $document = $session->getDocument();
        $save = $session->render()->getBounds($document->getElementById('save'))[0];
        $session->pointer($save->x + 2, $save->y + 2);
        $tip = $document->getElementById('tip');
        self::assertNotEmpty($session->render()->getBounds($tip));
        $inside = $session->render()->getBounds($document->getElementById('inside'))[0];
        $session->pointer($inside->x + 2, $inside->y + 2);
        self::assertNotEmpty($session->render()->getBounds($tip));
        $session->pointer(499, 199);
        self::assertSame([], $session->render()->getBounds($tip));
    }

    public function testEscapeDoesNotReopenUnchangedInterestOrCloseTheAutoPopover()
    {
        $session = new HtmlRenderer()->createSession(self::HTML, new RenderingContext(500, 200));
        $document = $session->getDocument();
        $tip = $document->getElementById('tip');
        $menu = $document->getElementById('menu');
        $session->showPopover($menu);
        $session->focus($document->getElementById('save'));
        self::assertNotEmpty($session->render()->getBounds($tip));
        self::assertNotEmpty($session->render()->getBounds($menu));
        $session->key(new KeyboardEvent('Escape'));
        self::assertSame([], $session->render()->getBounds($tip));
        self::assertNotEmpty($session->render()->getBounds($menu));
        $session->focus($document->getElementById('field'));
        $session->focus($document->getElementById('save'));
        self::assertNotEmpty($session->render()->getBounds($tip));
    }

    public function testToggleReentrancyAndDetachedInvokersDoNotKeepStaleInterest()
    {
        $session = new HtmlRenderer()->createSession(self::HTML, new RenderingContext(500, 200));
        $document = $session->getDocument();
        $tip = $document->getElementById('tip');
        $calls = 0;
        $session->on('beforetoggle', static function ($event) use ($session, $document, &$calls) {
            if ('open' === $event->data['newState']) {
                ++$calls;
                $session->focus($document->getElementById('field'));
                $session->render();
            }
        });
        $session->focus($document->getElementById('save'));
        self::assertSame([], $session->render()->getBounds($tip));
        self::assertSame(1, $calls);
        $document->getElementById('save')->remove();
        self::assertSame([], $session->render()->getBounds($tip));
    }
}
