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
use Symfony\Component\HtmlRenderer\Event\DocumentEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\RenderSession;

final class ShadowInteractionTest extends TestCase
{
    public function testFocusTraversalEditingAndEventsUseActiveComposedNodes()
    {
        $session = (new HtmlRenderer())->createSession('<style>user-card{display:block}</style><user-card><template shadowrootmode="open"><button id="inner">Go</button><input id="field" value="A"></template></user-card><button id="outer">Outside</button>', new RenderingContext(400, 300));
        $inner = $this->source($session, '#inner');
        $field = $this->source($session, '#field');
        $events = [];
        $host = $session->getDocument()->querySelector('user-card');
        $session->on('input', static function ($event) use (&$events, $field): void { $events[] = ['host', $event->target === $field]; }, $host);
        $session->on('input', static function ($event) use (&$events): void { $events[] = ['root', true]; });
        self::assertTrue($session->focusNext());
        self::assertSame($inner, $session->getFocusedElement());
        self::assertTrue($session->focusNext());
        self::assertSame($field, $session->getFocusedElement());
        $session->setValue($field, 'Edited', true);
        self::assertSame('Edited', $session->getValue($field));
        self::assertSame([['host', true], ['root', true]], $events);
        self::assertTrue($session->focusNext());
        self::assertSame($session->getDocument()->getElementById('outer'), $session->getFocusedElement());
    }

    public function testHostInertStateBlocksShadowFocusAndHitTesting()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><button id="inner">Go</button></template></user-card>', new RenderingContext(400, 300));
        $inner = $this->source($session, '#inner');
        $session->getDocument()->querySelector('user-card')->setAttribute('inert', '');
        $result = $session->render();
        $bounds = $result->getBounds($inner)[0];
        self::assertNotSame($inner, $result->hitTest($bounds->x + 1, $bounds->y + 1));
        $this->expectException(\InvalidArgumentException::class);
        $session->focus($inner);
    }

    public function testRevokedCachedRootsCannotReceiveDefaultActionsOrEvents()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><input id="field" value="A"></template></user-card>', new RenderingContext(400, 300));
        $field = $this->source($session, '#field');
        $session->focus($field);
        $session->getDocument()->querySelector('template')->innerHTML = '<p>Replacement</p>';
        self::assertNull($session->getFocusedElement());
        self::assertSame([], $session->render()->getBounds($field));
        $this->expectException(\InvalidArgumentException::class);
        $session->dispatch(new DocumentEvent('input', $field));
    }

    public function testFormsLabelsAndRadioGroupsRemainWithinTheirNativeRoots()
    {
        $session = (new HtmlRenderer())->createSession('<form id="form"><input id="shared" name="light" value="L"><input id="light-radio" type="radio" name="group" checked></form><user-card><template shadowrootmode="open"><form id="form"><label id="label" for="shared">Label</label><input id="shared" name="shadow" value="S"><input id="shadow-radio" type="radio" name="group" checked></form></template></user-card>', new RenderingContext(400, 300));
        $shadowForm = $this->source($session, 'user-card form');
        $shadowInput = $this->source($session, 'user-card #shared');
        $shadowRadio = $this->source($session, '#shadow-radio');
        $label = $this->source($session, '#label');
        $lightRadio = $session->getDocument()->getElementById('light-radio');
        $session->setChecked($shadowRadio, true);
        self::assertTrue($session->isChecked($lightRadio));
        self::assertTrue($session->isChecked($shadowRadio));
        $session->activate($label);
        self::assertSame($shadowInput, $session->getFocusedElement());
        $submission = $session->submit($shadowForm);
        self::assertNotNull($submission);
        self::assertSame(['shadow' => 'S', 'group' => 'on'], $submission->values);
    }

    public function testEmbeddedShadowDocumentUsesTheActiveHostingWitness()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><iframe id="frame" srcdoc="&lt;p&gt;Embedded&lt;/p&gt;"></iframe></template></user-card>', new RenderingContext(400, 300));
        $frame = $this->source($session, '#frame');
        $child = $session->getEmbeddedSession($frame);
        self::assertNotNull($child);
        self::assertFalse($frame->isConnected);
        $text = implode(' ', array_column(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']), 'text'));
        self::assertStringContainsString('Embedded', $text);
        $session->getDocument()->querySelector('user-card')->remove();
        self::assertNull($session->getEmbeddedSession($frame));
        self::assertSame([], $session->render()->getBounds($frame));
    }

    public function testForeignAdoptionAndDetachedShadowControlsAreRejected()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><input id="field" value="A"></template></user-card>', new RenderingContext(400, 300));
        $field = $this->source($session, '#field');
        $foreign = \Dom\HTMLDocument::createFromString('<!doctype html><html><body></body></html>');
        $foreign->body->appendChild($foreign->adoptNode($field));
        $this->expectException(\InvalidArgumentException::class);
        $session->setValue($field, 'Foreign');
    }

    public function testNestedNativeHostingIsRevokedWhenAnInnerHostLeavesItsRoot()
    {
        $session = (new HtmlRenderer())->createSession('<outer-card><template shadowrootmode="open"><inner-card><template shadowrootmode="open"><input id="field" value="A"></template></inner-card></template></outer-card>', new RenderingContext(400, 300));
        $field = $this->source($session, '#field');
        $innerHost = $this->source($session, 'inner-card');
        $session->focus($field);
        $session->setValue($field, 'Nested');
        self::assertSame('Nested', $session->getValue($field));
        $innerHost->remove();
        self::assertNull($session->getFocusedElement());
        self::assertSame([], $session->render()->getBounds($field));
        $this->expectException(\InvalidArgumentException::class);
        $session->setValue($field, 'Revoked');
    }

    public function testSameDocumentDetachedFragmentsAreNotActiveShadowRoots()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><input id="field" value="A"></template></user-card>', new RenderingContext(400, 300));
        $field = $this->source($session, '#field');
        $fragment = $session->getDocument()->createDocumentFragment();
        $fragment->appendChild($field);
        self::assertSame($session->getDocument(), $field->ownerDocument);
        $this->expectException(\InvalidArgumentException::class);
        $session->setValue($field, 'Unhosted');
    }

    public function testSlottedFocusHoverAndEventPathsFollowTheComposedAncestors()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><style>:host{display:block}:host(:hover){color:red}:host(:focus-within){background-color:blue}</style><slot id="slot"></slot></template><button id="light">Light</button></user-card>', new RenderingContext(400, 300));
        $slot = $this->source($session, '#slot');
        $light = $session->getDocument()->getElementById('light');
        $host = $session->getDocument()->querySelector('user-card');
        $path = [];
        $session->on('custom', static function () use (&$path): void { $path[] = 'slot'; }, $slot);
        $session->on('custom', static function () use (&$path): void { $path[] = 'host'; }, $host);
        $session->dispatch(new DocumentEvent('custom', $light));
        self::assertSame(['slot', 'host'], $path);
        $session->focus($light);
        self::assertSame('blue', $session->render()->getComputedStyle($host)['background-color']);
        $bounds = $session->render()->getBounds($light)[0];
        $session->pointer($bounds->x + 1, $bounds->y + 1);
        self::assertSame('red', $session->render()->getComputedStyle($host)['color']);
    }

    public function testModalInAShadowRootKeepsComposedFocusAndHitTestingInsideIt()
    {
        $session = (new HtmlRenderer())->createSession('<button id="outside">Outside</button><user-card><template shadowrootmode="open"><dialog id="dialog"><button id="inside">Inside</button></dialog></template></user-card>', new RenderingContext(400, 300));
        $dialog = $this->source($session, '#dialog');
        $inside = $this->source($session, '#inside');
        $session->showModal($dialog);
        self::assertSame($inside, $session->getFocusedElement());
        $result = $session->render();
        $bounds = $result->getBounds($inside)[0];
        self::assertSame($inside, $result->hitTest($bounds->x + 1, $bounds->y + 1));
        self::assertTrue($session->focusNext());
        self::assertSame($inside, $session->getFocusedElement());
    }

    public function testClickHandlerReplacingTheRootSuppressesTheOldSubmitDefaultAction()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><form><input name="value" value="A"><button id="submit">Submit</button></form></template></user-card>', new RenderingContext(400, 300));
        $button = $this->source($session, '#submit');
        $submitted = 0;
        $session->on('submit', static function () use (&$submitted): void { ++$submitted; });
        $session->on('click', static function () use ($session): void {
            $session->getDocument()->querySelector('template')->innerHTML = '<p>Replacement</p>';
            $session->render();
        }, $button);
        self::assertNull($session->activate($button));
        self::assertSame(0, $submitted);
        self::assertSame([], $session->render()->getBounds($button));
    }

    public function testClickHandlerAdoptingAControlCannotRunItsChangeDefaultAction()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><input id="check" type="checkbox"></template></user-card>', new RenderingContext(400, 300));
        $check = $this->source($session, '#check');
        $foreign = \Dom\HTMLDocument::createFromString('<!doctype html><html><body></body></html>');
        $changed = 0;
        $session->on('change', static function () use (&$changed): void { ++$changed; });
        $session->on('click', static function () use ($foreign, $check): void { $foreign->body->appendChild($foreign->adoptNode($check)); }, $check);
        self::assertNull($session->activate($check));
        self::assertSame(0, $changed);
        self::assertFalse($check->hasAttribute('checked'));
        self::assertSame([], $session->render()->getBounds($check));
    }

    public function testAnEarlierValidTemplateRevokesThePreviousRootDuringClickDispatch()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><input id="check" type="checkbox"></template></user-card>', new RenderingContext(400, 300));
        $check = $this->source($session, '#check');
        $changed = 0;
        $session->on('change', static function () use (&$changed): void { ++$changed; });
        $session->on('click', static function () use ($session): void {
            $document = $session->getDocument();
            $template = $document->createElement('template');
            $template->setAttribute('shadowrootmode', 'open');
            $template->innerHTML = '<p>New root</p>';
            $host = $document->querySelector('user-card');
            $host->insertBefore($template, $host->firstChild);
        }, $check);
        self::assertNull($session->activate($check));
        self::assertSame(0, $changed);
        self::assertSame([], $session->render()->getBounds($check));
    }

    public function testAnEarlierInvalidTemplateDoesNotRevokeTheActiveRoot()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><input id="field" value="A"></template></user-card>', new RenderingContext(400, 300));
        $field = $this->source($session, '#field');
        $document = $session->getDocument();
        $template = $document->createElement('template');
        $template->setAttribute('shadowrootmode', 'invalid');
        $host = $document->querySelector('user-card');
        $host->insertBefore($template, $host->firstChild);
        $session->setValue($field, 'Still active');
        self::assertSame('Still active', $session->getValue($field));
        self::assertSame($field, $this->source($session, '#field'));
    }

    public function testAHostRejectedByCompositionRevokesItsCachedRootDuringClickDispatch()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><input id="check" type="checkbox"></template></user-card>', new RenderingContext(400, 300));
        $check = $this->source($session, '#check');
        $changed = 0;
        $session->on('change', static function () use (&$changed): void { ++$changed; });
        $session->on('click', static function () use ($session): void {
            $session->getDocument()->querySelector('user-card')->setAttribute(\Symfony\Component\HtmlRenderer\Css\Selector::HOST_ATTRIBUTE, 'already-attached');
        }, $check);
        self::assertNull($session->activate($check));
        self::assertSame(0, $changed);
        self::assertSame([], $session->render()->getBounds($check));
    }

    public function testComposedInspectionReturnsOriginalParentsWithoutComputingLayout()
    {
        $session = (new HtmlRenderer())->createSession('<user-card id="host"><template shadowrootmode="open"><slot id="slot" name="assigned"></slot><p id="inside">Inside</p></template><span id="light" slot="assigned">Light</span><span id="inactive">Unassigned</span></user-card>', new RenderingContext(400, 300));
        $source = $session->getDocument()->saveHtml();
        $tuples = iterator_to_array($session->getComposedElements(), false);
        self::assertNull((new \ReflectionProperty($session, 'result'))->getValue($session));
        $nodes = $parents = [];
        foreach ($tuples as [$element, $parent]) {
            if ($element->hasAttribute('id')) {
                $nodes[$element->getAttribute('id')] = $element;
                $parents[$element->getAttribute('id')] = $parent;
            }
        }
        self::assertSame(['host', 'slot', 'light', 'inside'], array_keys($nodes));
        self::assertSame($session->getDocument()->getElementById('light'), $nodes['light']);
        self::assertSame($nodes['slot'], $parents['light']);
        self::assertSame($nodes['host'], $parents['inside']);
        self::assertSame($source, $session->getDocument()->saveHtml());
        $result = $session->render();
        iterator_to_array($session->getComposedElements(), false);
        self::assertSame($result, $session->render());
    }

    public function testComposedInspectionRevalidatesNodesAfterTheConsumerDetachesTheirHost()
    {
        $session = (new HtmlRenderer())->createSession('<user-card id="host"><template shadowrootmode="open"><input id="inside"></template></user-card>', new RenderingContext(400, 300));
        $ids = [];
        foreach ($session->getComposedElements() as [$element]) {
            if ('host' === $element->getAttribute('id')) {
                $element->remove();
            }
            if ($element->hasAttribute('id')) {
                $ids[] = $element->getAttribute('id');
            }
        }
        self::assertSame(['host'], $ids);
    }

    private function source(RenderSession $session, string $selector): \Dom\Element
    {
        $session->render();
        $view = (new \ReflectionProperty($session, 'shadowView'))->getValue($session);

        return $view['owners'][$view['document']->querySelector($selector)];
    }
}
