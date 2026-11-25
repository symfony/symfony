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

final class HtmlRendererTest extends TestCase
{
    public function testHostInvalidationSubscriptionsCoalesceUntilTheNextFrame()
    {
        $session = (new HtmlRenderer())->createSession('<input>', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->render();
        $notifications = 0;
        $unsubscribe = $session->onInvalidation(static function () use (&$notifications) { ++$notifications; });
        $session->setValue($input, 'first');
        $session->setValue($input, 'second');
        self::assertSame(1, $notifications);
        $session->render();
        $session->invalidate();
        self::assertSame(2, $notifications);
        $session->render();
        $unsubscribe();
        $session->setValue($input, 'third');
        self::assertSame(2, $notifications);
    }

    public function testApplicationCanSetLiveControlStateWithoutFocusOrSyntheticInput()
    {
        $session = (new HtmlRenderer())->createSession('<form><input id="text" name="text" value="default" readonly><input id="check" type="checkbox" name="check"><input type="radio" name="r" id="one" checked><input type="radio" name="r" id="two"><select id="select"><option>A</option><option>B</option></select></form>', new RenderingContext(300, 200));
        $document = $session->getDocument();
        $source = $document->saveHtml();
        $events = [];
        $session->on('input', static function () use (&$events) { $events[] = 'input'; });
        $session->on('change', static function () use (&$events) { $events[] = 'change'; });
        $session->setValue($document->getElementById('text'), 'application value');
        $session->setChecked($document->getElementById('check'), true);
        $session->setChecked($document->getElementById('two'), true);
        $session->select($document->getElementById('select'), ['B'], false);
        self::assertSame('application value', $session->getValue($document->getElementById('text')));
        self::assertTrue($session->isChecked($document->getElementById('check')));
        self::assertFalse($session->isChecked($document->getElementById('one')));
        self::assertTrue($session->isChecked($document->getElementById('two')));
        self::assertSame(['B'], $session->getSelectedValues($document->getElementById('select')));
        self::assertSame([], $events);
        self::assertNull($session->getFocusedElement());
        self::assertSame($source, $document->saveHtml());
        $session->reset($document->querySelector('form'));
        self::assertSame('default', $session->getValue($document->getElementById('text')));
        self::assertFalse($session->isChecked($document->getElementById('check')));
    }

    public function testMaxlengthLimitsTypingAndPastesWithoutTruncatingAssignedValues()
    {
        $session = (new HtmlRenderer())->createSession('<input maxlength="5" value="ab"><textarea maxlength="3">ab</textarea>', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        $session->key(new KeyboardEvent('Text', 'c'));
        $session->key(new KeyboardEvent('Text', 'defgh'));
        self::assertSame('abcde', $session->getValue($input));
        $session->key(new KeyboardEvent('Text', 'z'));
        self::assertSame('abcde', $session->getValue($input));
        $session->setTextSelection($input, 1, 4);
        $session->key(new KeyboardEvent('Text', 'é中XY'));
        self::assertSame('aé中Xe', $session->getValue($input));
        $session->input('longer than five');
        self::assertSame('longer than five', $session->getValue($input));
        $textarea = $session->getDocument()->querySelector('textarea');
        $session->focus($textarea);
        $session->key(new KeyboardEvent('Text', "c\nd"));
        self::assertSame('abc', $session->getValue($textarea));
    }

    public function testTextFallbackControlsIgnoreFilePresetAndEditColorDefault()
    {
        $session = (new HtmlRenderer())->createSession('<input id="file" type="file" value="preset-path"><input id="color" type="color">', new RenderingContext(300, 200));
        $file = $session->getDocument()->getElementById('file');
        $color = $session->getDocument()->getElementById('color');
        self::assertSame('', $session->getValue($file));
        self::assertSame('#000000', $session->getValue($color));
        $session->focus($file);
        $session->key(new KeyboardEvent('Text', 'report.txt'));
        self::assertSame('report.txt', $session->getValue($file));
        $session->focus($color);
        $session->setTextSelection($color, 0, 7);
        $session->key(new KeyboardEvent('Text', '#aabbcc'));
        self::assertSame('#aabbcc', $session->getValue($color));
        self::assertSame('preset-path', $file->getAttribute('value'));
    }

    public function testParserSerializationOverheadDoesNotIncreaseEditingBudget()
    {
        $session = (new HtmlRenderer(8))->createSession('<input>', new RenderingContext(100, 100));
        $session->focus($session->getDocument()->querySelector('input'));
        $this->expectException(\LengthException::class);
        $session->input('123456789');
    }

    public function testDescendantActivationUsesSummaryAndLabelSemantics()
    {
        $session = (new HtmlRenderer())->createSession('<details><summary><b>Open</b></summary><p>Content</p></details><label for="c"><b>Toggle</b></label><input id="c" type="checkbox">', new RenderingContext(300, 200));
        $document = $session->getDocument();
        $session->activate($document->querySelector('summary b'));
        self::assertTrue($document->querySelector('details')->hasAttribute('open'));
        $session->activate($document->querySelector('label b'));
        self::assertTrue($session->isChecked($document->getElementById('c')));
        self::assertSame($document->getElementById('c'), $session->getFocusedElement());
    }

    public function testNodeUserAgentDeclarationsKeepShorthandOrder()
    {
        $session = (new HtmlRenderer())->createSession('<p>Text</p>', new RenderingContext(300, 200));
        $paragraph = $session->getDocument()->querySelector('p');
        $session->setUserAgentStyle($paragraph, ['margin-left' => '10px', 'margin' => '20px']);
        self::assertSame('20px', $session->render()->getComputedStyle($paragraph)['margin-left']);
        $session->setUserAgentStyle($paragraph, ['MARGIN' => '1px', 'margin-left' => '10px', 'margin' => '20px']);
        self::assertSame('20px', $session->render()->getComputedStyle($paragraph)['margin-left']);
    }

    public function testNodeUserAgentStylesPreserveSourceAndReuseUnchangedFrames()
    {
        $session = (new HtmlRenderer())->createSession('<style>input{color:red}</style><form><input></form>', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $source = $session->getDocument()->saveHtml();
        $session->setUserAgentStyle($input, ['width' => '32.5px', 'color' => 'blue']);
        $session->setUserAgentStyle($input, ['content' => '"Host label"'], 'after');
        $first = $session->render();
        self::assertSame('red', $first->getComputedStyle($input)['color']);
        self::assertSame(38.5, $first->getBounds($input)[0]->width);
        $session->setUserAgentStyle($input, ['width' => '32.5px', 'color' => 'blue']);
        self::assertSame($first, $session->render());
        $session->reset($session->getDocument()->querySelector('form'));
        self::assertSame(38.5, $session->render()->getBounds($input)[0]->width);
        self::assertSame($source, $session->getDocument()->saveHtml());
        $session->setUserAgentStyle($input, []);
        self::assertNotSame(38.5, $session->render()->getBounds($input)[0]->width);
    }

    public function testDocumentFocusRevealsControlThroughTransformedScrollPort()
    {
        $session = (new HtmlRenderer())->createSession('<div style="height:20px;overflow:auto;font-size:10px;line-height:10px;transform:scale(2);transform-origin:0 0"><p style="margin:0;height:40px"></p><input style="height:10px;min-height:0;line-height:10px;border:0;padding:0"></div>', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        self::assertSame([0.0, 30.0], $session->getScroll($input->parentElement));
        $bounds = $session->render()->getBounds($input)[0];
        self::assertSame(10.0, $bounds->y);
    }

    public function testTopLayerFocusDoesNotScrollItsSemanticAncestors()
    {
        $session = (new HtmlRenderer())->createSession('<section style="height:20px;overflow:auto"><p style="height:60px"></p><dialog><input></dialog></section>', new RenderingContext(300, 200));
        $session->showModal($session->getDocument()->querySelector('dialog'));
        self::assertSame([0.0, 0.0], $session->getScroll($session->getDocument()->querySelector('section')));
    }

    public function testScrollStateClampsToContentAndUpdatesAfterNativeMutations()
    {
        $session = (new HtmlRenderer())->createSession('<div style="height:20px;overflow:auto"><p style="margin:0;height:60.5px">Content</p></div>', new RenderingContext(300, 200));
        $container = $session->getDocument()->querySelector('div');
        $session->scroll($container, 100, 100);
        self::assertSame([0.0, 40.5], $session->getScroll($container));
        self::assertSame(40.5, $session->render()->getScrollGeometry($container)->maxY);
        $session->getDocument()->querySelector('p')->setAttribute('style', 'margin:0;height:5px');
        self::assertSame([0.0, 0.0], $session->getScroll($container));
    }

    public function testRangeKeyboardUsesStepBoundariesAndDocumentDirection()
    {
        $session = (new HtmlRenderer())->createSession('<input type="range" min="0.15" max="0.49" step="0.1" value="0.32" dir="rtl">', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        self::assertSame('0.35', $session->getValue($input));
        $session->key(new KeyboardEvent('ArrowLeft'));
        self::assertSame('0.45', $session->getValue($input));
        $session->key(new KeyboardEvent('Home'));
        self::assertSame('0.15', $session->getValue($input));
        $session->key(new KeyboardEvent('End'));
        self::assertSame('0.45', $session->getValue($input));
    }

    public function testOptionActivationPreservesIdentityForDuplicateValues()
    {
        $session = (new HtmlRenderer())->createSession('<style>option:checked{color:red}</style><select multiple size="3"><option value="same">A</option><option value="same">B</option></select>', new RenderingContext(300, 200));
        $options = iterator_to_array($session->getDocument()->querySelectorAll('option'));
        $session->activate($options[1]);
        self::assertSame(['same'], $session->getSelectedValues($session->getDocument()->querySelector('select')));
        self::assertNotSame('red', $session->render()->getComputedStyle($options[0])['color']);
        self::assertSame('red', $session->render()->getComputedStyle($options[1])['color']);
        $session->activate($options[0]);
        self::assertSame(['same', 'same'], $session->getSelectedValues($session->getDocument()->querySelector('select')));
    }

    public function testOptionActivationUpdatesIndependentSelectionAndKeepsSelectFocus()
    {
        $session = (new HtmlRenderer())->createSession('<select multiple size="3"><option value="a">A</option><option value="b">B</option><optgroup disabled><option value="c">C</option></optgroup></select>', new RenderingContext(300, 200));
        $select = $session->getDocument()->querySelector('select');
        $options = iterator_to_array($select->querySelectorAll('option'));
        $session->activate($options[0]);
        $session->activate($options[1]);
        self::assertSame(['a', 'b'], $session->getSelectedValues($select));
        self::assertSame($select, $session->getFocusedElement());
        $session->activate($options[0]);
        $session->activate($options[2]);
        self::assertSame(['b'], $session->getSelectedValues($select));
        self::assertFalse($options[1]->hasAttribute('selected'));
    }

    public function testSubmissionValidatesSelectedOptionsAndSkipsBarredControls()
    {
        $session = (new HtmlRenderer())->createSession('<form><input type="hidden" name="h" required><select name="s" required multiple><option value="" selected>Empty value</option></select><input readonly required name="r"><button>Submit</button></form>', new RenderingContext(300, 200));
        $form = $session->getDocument()->querySelector('form');
        self::assertSame(['h' => '', 's' => [''], 'r' => ''], $session->submit($form)->values);
        $session->select($session->getDocument()->querySelector('select'), []);
        self::assertNull($session->submit($form));
    }

    public function testSubmissionRejectsSubmitterFromAnotherForm()
    {
        $session = (new HtmlRenderer())->createSession('<form id="one"></form><form><button>Other</button></form>', new RenderingContext(300, 200));
        $this->expectException(\InvalidArgumentException::class);
        $session->submit($session->getDocument()->getElementById('one'), $session->getDocument()->querySelector('button'));
    }

    public function testInertSubtreesRemainVisibleButCannotReceiveFocusOrActivation()
    {
        $session = (new HtmlRenderer())->createSession('<section inert><input type="checkbox"><button>Inside</button></section><button>Outside</button>', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        self::assertNotEmpty($session->render()->getBounds($input));
        $session->activate($input);
        self::assertFalse($session->isChecked($input));
        self::assertNull($session->getFocusedElement());
        $session->focusNext();
        self::assertSame('Outside', $session->getFocusedElement()->textContent);
        $session->getDocument()->body->setAttribute('inert', '');
        self::assertNull($session->getFocusedElement());
    }

    public function testHostStatesInvalidateStylesWithoutMutatingNativeDocument()
    {
        $session = (new HtmlRenderer())->createSession('<style>p:busy { color: red }</style><p>Hello</p>', new RenderingContext(100, 80));
        $element = $session->getDocument()->querySelector('p');
        $source = $session->getDocument()->saveHtml();
        $first = $session->render();
        $session->setState($element, 'busy');
        self::assertSame('red', $session->render()->getComputedStyle($element)['color']);
        $busy = $session->render();
        $session->setState($element, 'busy');
        self::assertSame($busy, $session->render());
        $session->setState($element, 'busy', false);
        self::assertSame($first->getComputedStyle($element)['color'], $session->render()->getComputedStyle($element)['color']);
        self::assertSame($source, $session->getDocument()->saveHtml());
    }

    public function testHostCannotOverrideSessionManagedFocusState()
    {
        $session = (new HtmlRenderer())->createSession('<p>Hello</p>', new RenderingContext(100, 80));
        $this->expectException(\InvalidArgumentException::class);
        $session->setState($session->getDocument()->querySelector('p'), 'focus');
    }

    public function testResetRestoresDefaultsIncludingExternalControlsAndEditingHistory()
    {
        $session = (new HtmlRenderer())->createSession('<form id="f"><input name="a" value="initial"><input type="checkbox" name="c" checked><textarea name="t">default</textarea><select name="s"><option>a</option><option selected>b</option></select><button type="reset">Reset</button></form><input form="f" name="external" value="outside"><input name="other" value="other">', new RenderingContext(400, 300));
        $document = $session->getDocument();
        $input = $document->querySelector('input');
        $session->focus($input);
        $session->input('changed');
        $session->activate($document->querySelector('[type="checkbox"]'));
        $session->select($document->querySelector('select'), ['a']);
        $external = $document->querySelector('[name="external"]');
        $session->focus($external);
        $session->input('changed outside');
        $session->activate($document->querySelector('button'));
        self::assertSame('initial', $session->getValue($input));
        self::assertTrue($session->isChecked($document->querySelector('[type="checkbox"]')));
        self::assertSame(['b'], $session->getSelectedValues($document->querySelector('select')));
        self::assertSame('outside', $session->getValue($external));
        $session->focus($input);
        $session->key(new KeyboardEvent('z', '', false, true));
        self::assertSame('initial', $session->getValue($input));
    }

    public function testResetIsCancelableAndReadsCurrentDefaultAttributes()
    {
        $session = (new HtmlRenderer())->createSession('<form><input value="initial"></form>', new RenderingContext(100, 80));
        $input = $session->getDocument()->querySelector('input');
        $form = $session->getDocument()->querySelector('form');
        $session->focus($input);
        $session->input('changed');
        $remove = $session->on('reset', static fn ($event) => $event->preventDefault(), $form);
        $session->reset($form);
        self::assertSame('changed', $session->getValue($input));
        $remove();
        $input->setAttribute('value', 'new default');
        $session->reset($form);
        self::assertSame('new default', $session->getValue($input));
    }

    public function testStandaloneRenderingPreservesNativeNodeIdentityAndFractionalGeometry()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p { width: 12.5px; color: red }</style><p>Hello</p>');
        $element = $document->querySelector('p');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(100.5, 80.25));
        self::assertSame('red', $result->getComputedStyle($element)['color']);
        self::assertSame(12.5, $result->getBounds($element)[0]->width);
        self::assertNotEmpty($result->getDisplayList()->getOperations());
        self::assertSame($document, $result->getDocument());
    }

    public function testMutationAndViewportChangeInvalidateRetainedResult()
    {
        $session = (new HtmlRenderer())->createSession('<p style="width: 50%">A</p>', new RenderingContext(100, 80));
        $first = $session->render();
        self::assertSame($first, $session->render());
        $element = $session->getDocument()->querySelector('p');
        $element->textContent = 'Changed';
        self::assertNotSame($first, $changed = $session->render());
        self::assertSame(50.0, $changed->getBounds($element)[0]->width);
        $session->setContext(new RenderingContext(101, 80));
        self::assertSame(50.5, $session->render()->getBounds($element)[0]->width);
    }

    public function testSelectionPseudoClassesFollowStateInsteadOfDefaultAttributes()
    {
        $session = (new HtmlRenderer())->createSession('<style>select:has(option[value="b"]:checked) { color: red }</style><select><option value="a" selected>A</option><option value="b">B</option></select>', new RenderingContext(400, 100));
        $select = $session->getDocument()->querySelector('select');
        $session->select($select, ['b']);
        self::assertSame('red', $session->render()->getComputedStyle($select)['color']);
        self::assertTrue($select->querySelector('option')->hasAttribute('selected'));
    }

    public function testMultipleSelectionIsDocumentStateAndSubmitsEverySuccessfulValue()
    {
        $session = (new HtmlRenderer())->createSession('<form><select name="languages" multiple required><option value="php" selected>PHP</option><option value="go">Go</option><option value="blocked" disabled>Blocked</option></select></form>', new RenderingContext(400, 200));
        $document = $session->getDocument();
        $select = $document->querySelector('select');
        $session->select($select, ['go', 'blocked']);
        self::assertSame(['go'], $session->getSelectedValues($select));
        self::assertTrue($select->querySelector('option')->hasAttribute('selected'));
        self::assertSame(['languages' => ['go']], $session->submit($document->querySelector('form'))?->values);
        $text = array_column(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']), 'text');
        self::assertContains('Go', $text);
    }

    public function testFormSubmissionAndFocusRemainDocumentState()
    {
        $session = (new HtmlRenderer())->createSession('<form><input name="name" required><input type="checkbox" name="yes" checked><button name="action" value="save">Save</button></form>', new RenderingContext(400, 300));
        $document = $session->getDocument();
        $input = $document->querySelector('input');
        $session->focus($input);
        self::assertSame($input, $session->getFocusedElement());
        self::assertNull($session->submit($document->querySelector('form')));
        $session->input('Ada');
        $submission = $session->submit($document->querySelector('form'), $document->querySelector('button'));
        self::assertSame(['name' => 'Ada', 'yes' => 'on', 'action' => 'save'], $submission->values);
    }

    public function testControlStateSurvivesNativeWrapperCollectionBeforeTheFirstRender()
    {
        $session = (new HtmlRenderer())->createSession('<input name="a"><input name="b">', new RenderingContext(120, 80));
        $session->focus($session->getDocument()->querySelector('[name="a"]'));
        $session->input('Retained');
        $session->focus($session->getDocument()->querySelector('[name="b"]'));
        gc_collect_cycles();
        self::assertSame('Retained', $session->getValue($session->getDocument()->querySelector('[name="a"]')));
    }

    public function testNativeDocumentAndLaterMutationsRespectTheDocumentBudget()
    {
        $renderer = new HtmlRenderer(200);
        $session = $renderer->createSession('<p>A</p>', new RenderingContext(100, 80));
        $session->render();
        $session->getDocument()->querySelector('p')->textContent = str_repeat('A', 1000);
        $this->expectException(\LengthException::class);
        $session->render();
    }

    public function testDataImagesProducePortableDrawingOperations()
    {
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6R5sAAAAASUVORK5CYII=';
        $result = (new HtmlRenderer())->render('<img style="width: 12.5px" src="data:image/png;base64,'.$png.'"><svg width="10" height="5"><rect width="10" height="5"/></svg>', new RenderingContext(100, 80));
        $images = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => \in_array($op['type'], ['image', 'svg'], true)));
        self::assertCount(2, $images);
        self::assertSame(12.5, $images[0]['width']);
        self::assertSame('image/png', $images[0]['mime-type']);
        self::assertSame('svg', $images[1]['type']);
        self::assertIsString($images[0]['data']);
    }

    public function testAnimatedRelativeFontSizesAreComputedBeforeInheritance()
    {
        $session = (new HtmlRenderer())->createSession('<style>@keyframes grow { from { font-size: 1em } to { font-size: 2em } } p { animation: grow 1s linear forwards }</style><p><span>Text</span></p>', new RenderingContext(400, 100));
        $session->render();
        $session->advance(0.5);
        $result = $session->render();
        self::assertSame('24px', $result->getComputedStyle($session->getDocument()->querySelector('p'))['font-size']);
        self::assertSame('24px', $result->getComputedStyle($session->getDocument()->querySelector('span'))['font-size']);
    }

    public function testAnimationTimeIsAdvancedExplicitlyWithoutAnEventLoop()
    {
        $session = (new HtmlRenderer())->createSession('<style>@keyframes fade { from { opacity: 0 } to { opacity: 1 } } p { animation: fade 1s linear forwards }</style><p>Hello</p>', new RenderingContext(400, 100));
        $element = $session->getDocument()->querySelector('p');
        self::assertEquals(0, $session->render()->getComputedStyle($element)['opacity']);
        $session->advance(0.375);
        self::assertEqualsWithDelta(0.375, (float) $session->render()->getComputedStyle($element)['opacity'], 0.000001);
        $session->advance(0.625);
        self::assertEquals(1, $session->render()->getComputedStyle($element)['opacity']);
    }

    public function testGeneratedContentCreatesIndependentBoxesWithoutChangingDom()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p::before { content: "Prefix " attr(data-title); color: red } p::after { content: "!" }</style><p data-title="A">Text</p>');
        $before = $document->saveHtml();
        $result = (new HtmlRenderer())->render($document, new RenderingContext(400, 100));
        $text = array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']), 'text');
        self::assertContains('Prefix A', $text);
        self::assertContains('!', $text);
        self::assertSame($before, $document->saveHtml());
        self::assertGreaterThan(1, \count($result->getBounds($document->querySelector('p'))));
    }

    public function testCustomPropertiesCannotReplaceInteractiveState()
    {
        $session = (new HtmlRenderer())->createSession('<input value="Real" style="--html-control-value: Forged"><div style="--html-scroll-y: 100"><p>Text</p></div>', new RenderingContext(400, 100));
        $result = $session->render();
        $text = array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']), 'text');
        self::assertContains('Real', $text);
        self::assertNotContains('Forged', $text);
        $paragraph = $session->getDocument()->querySelector('p');
        self::assertGreaterThanOrEqual($result->getBounds($paragraph->parentElement)[0]->y, $result->getBounds($paragraph)[0]->y);
        self::assertSame('Forged', $result->getComputedStyle($session->getDocument()->querySelector('input'))['--html-control-value']);
    }

    public function testControlsGenerateVisualFragmentsFromDocumentState()
    {
        $session = (new HtmlRenderer())->createSession('<input id="name" name="name"><input type="checkbox" id="check" checked><input type="password" value="secret">', new RenderingContext(400, 100));
        $session->focus($session->getDocument()->getElementById('name'));
        $session->input('Ada');
        $operations = $session->render()->getDisplayList()->getOperations();
        $text = array_column(array_filter($operations, static fn (array $op) => 'text' === $op['type']), 'text');
        self::assertContains('Ada', $text);
        self::assertContains('✓', $text);
        self::assertNotContains('secret', $text);
        self::assertGreaterThan(0, $session->render()->getBounds($session->getDocument()->getElementById('check'))[0]->height);
    }

    public function testCheckedStateDoesNotRewriteTheDefaultAttribute()
    {
        $session = (new HtmlRenderer())->createSession('<style>input:checked { color: red } input[checked] { opacity: 0.5 }</style><input type="checkbox" checked>', new RenderingContext(400, 100));
        $element = $session->getDocument()->querySelector('input');
        $session->activate($element);
        self::assertTrue($element->hasAttribute('checked'));
        self::assertFalse($session->isChecked($element));
        self::assertSame('0.5', $session->render()->getComputedStyle($element)['opacity']);
        self::assertSame('#000000', $session->render()->getComputedStyle($element)['color']);
    }

    public function testLabelsAndDisclosureActivationUseDocumentSemantics()
    {
        $session = (new HtmlRenderer())->createSession('<label for="check">Toggle</label><input id="check" type="checkbox"><details name="group" open id="one"><summary>One</summary><p>First</p></details><details name="group" id="two"><summary>Two</summary><p>Second</p></details>', new RenderingContext(400, 200));
        $document = $session->getDocument();
        $session->activate($document->querySelector('label'));
        self::assertTrue($session->isChecked($document->getElementById('check')));
        self::assertSame($document->getElementById('check'), $session->getFocusedElement());
        $summary = $document->getElementById('two')->querySelector('summary');
        $session->activate($summary);
        self::assertTrue($summary->parentElement->hasAttribute('open'));
        self::assertFalse($document->getElementById('one')->hasAttribute('open'));
        $session->key(new KeyboardEvent('Enter'));
        self::assertFalse($summary->parentElement->hasAttribute('open'));
    }

    public function testActivationOwnsRadioGroupingAndCheckboxState()
    {
        $session = (new HtmlRenderer())->createSession('<input id="a" type="radio" name="group" checked><input id="b" type="radio" name="group"><input id="c" type="checkbox">', new RenderingContext(120, 80));
        $document = $session->getDocument();
        $session->activate($document->getElementById('b'));
        self::assertFalse($session->isChecked($document->getElementById('a')));
        self::assertTrue($session->isChecked($document->getElementById('b')));
        $session->activate($document->getElementById('c'));
        self::assertTrue($session->isChecked($document->getElementById('c')));
        $session->activate($document->getElementById('c'));
        self::assertFalse($session->isChecked($document->getElementById('c')));
    }

    public function testFocusWithinAndRequiredStatesAreResolvedOnNativeElements()
    {
        $session = (new HtmlRenderer())->createSession('<style>form:focus-within { color: red } input:invalid { opacity: 0.5 }</style><form><input required></form>', new RenderingContext(400, 100));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        self::assertSame('red', $session->render()->getComputedStyle($input->parentElement)['color']);
        self::assertSame('0.5', $session->render()->getComputedStyle($input)['opacity']);
        $session->input('Ada');
        self::assertArrayNotHasKey('opacity', $session->render()->getComputedStyle($input));
    }

    public function testFocusSkipsClosedDisclosureContentButIncludesVisibleOverrides()
    {
        $session = (new HtmlRenderer())->createSession('<details><summary>Open</summary><input id="hidden"></details><div style="visibility: hidden"><input id="visible" style="visibility: visible"></div>', new RenderingContext(400, 200));
        $session->focusNext();
        self::assertSame('summary', $session->getFocusedElement()->localName);
        $session->focusNext();
        self::assertSame('visible', $session->getFocusedElement()->id);
    }

    public function testFocusOrderSkipsHiddenAndDisabledControlsAndHonorsTabIndex()
    {
        $session = (new HtmlRenderer())->createSession('<input id="regular"><input id="hidden" style="display: none"><fieldset disabled><input id="disabled"></fieldset><input id="first" tabindex="1">', new RenderingContext(120, 80));
        $session->focusNext();
        self::assertSame('first', $session->getFocusedElement()->id);
        $session->focusNext();
        self::assertSame('regular', $session->getFocusedElement()->id);
        $session->focusNext();
        self::assertSame('first', $session->getFocusedElement()->id);
    }

    public function testPointerActivationPlacesTheCaretThroughLayoutTextRuns()
    {
        $session = (new HtmlRenderer())->createSession('<input value="ABCD">', new RenderingContext(400, 100));
        $element = $session->getDocument()->querySelector('input');
        $session->pointer(12, 8, true);
        $session->key(new KeyboardEvent('Text', 'X'));
        self::assertSame('AXBCD', $session->getValue($element));
    }

    public function testFocusedTextSelectionProducesBackendIndependentPaint()
    {
        $session = (new HtmlRenderer())->createSession('<input value="AB中">', new RenderingContext(400, 100));
        $element = $session->getDocument()->querySelector('input');
        $session->focus($element);
        $session->setTextSelection($element, 1, 2);
        $operations = $session->render()->getDisplayList()->getOperations();
        self::assertNotEmpty(array_filter($operations, static fn (array $op) => 'caret' === $op['type']));
        self::assertNotEmpty(array_filter($operations, static fn (array $op) => 'rectangle' === $op['type'] && '#b5d5ff' === ($op['color'] ?? null)));
    }

    public function testNormalizedKeyboardInputEditsGraphemesAndControls()
    {
        $session = (new HtmlRenderer())->createSession('<form><input id="text" name="text" value="Aé中"><select id="select" name="language"><option>PHP</option><option>Go</option></select><input id="number" type="number" value="2" step="0.5"></form>', new RenderingContext(400, 200));
        $document = $session->getDocument();
        $session->focus($document->getElementById('text'));
        $session->key(new KeyboardEvent('ArrowLeft'));
        $session->key(new KeyboardEvent('Backspace'));
        $session->key(new KeyboardEvent('Text', 'B'));
        self::assertSame('AB中', $session->getValue($document->getElementById('text')));
        $session->focus($document->getElementById('select'));
        $session->key(new KeyboardEvent('ArrowDown'));
        self::assertSame(['PHP'], $session->getSelectedValues($document->getElementById('select')));
        $session->key(new KeyboardEvent('ArrowDown'));
        $session->key(new KeyboardEvent('Enter'));
        self::assertSame(['Go'], $session->getSelectedValues($document->getElementById('select')));
        $session->focus($document->getElementById('number'));
        $session->key(new KeyboardEvent('ArrowUp'));
        self::assertSame('2.5', $session->getValue($document->getElementById('number')));
        self::assertSame('AB中', $session->key(new KeyboardEvent('Enter'))?->values['text']);
    }

    public function testSemanticListenersCanBeRemovedAndButtonActivationSubmits()
    {
        $session = (new HtmlRenderer())->createSession('<form><input name="name" value="Ada"><button>Save</button></form>', new RenderingContext(400, 100));
        $submissions = [];
        $remove = $session->on('submit', static function ($event) use (&$submissions) { $submissions[] = $event->data['submission']->values; });
        $session->activate($session->getDocument()->querySelector('button'));
        self::assertSame([['name' => 'Ada']], $submissions);
        $remove();
        $session->activate($session->getDocument()->querySelector('button'));
        self::assertCount(1, $submissions);
    }

    public function testSemanticEventsBubbleAndCanCancelSubmission()
    {
        $session = (new HtmlRenderer())->createSession('<form><input name="name" value="Ada"></form>', new RenderingContext(120, 80));
        $form = $session->getDocument()->querySelector('form');
        $events = [];
        $session->on('focus', static function ($event) use (&$events) { $events[] = $event->target->localName; });
        $session->on('submit', static function ($event) { $event->preventDefault(); });
        $session->focus($form->querySelector('input'));
        self::assertSame(['input'], $events);
        self::assertNull($session->submit($form));
    }

    public function testScrollingChangesDescendantGeometryWithoutChangingNodeIdentity()
    {
        $session = (new HtmlRenderer())->createSession('<div style="height: 10px; overflow: hidden"><p>Content</p></div>', new RenderingContext(120, 80));
        $element = $session->getDocument()->querySelector('p');
        $before = $session->render()->getBounds($element)[0];
        $session->scroll($element->parentElement, 0, 5.5);
        $after = $session->render()->getBounds($element)[0];
        self::assertSame($before->y - 5.5, $after->y);
        self::assertSame([0.0, 5.5], $session->getScroll($element->parentElement));
    }

    public function testTextNodesDoNotRepeatTheParentBoxDecorations()
    {
        $result = (new HtmlRenderer())->render('<p style="margin-left: 3px; background: red">Hello</p>', new RenderingContext(120, 80));
        $operations = $result->getDisplayList()->getOperations();
        $backgrounds = array_filter($operations, static fn (array $operation) => 'rectangle' === $operation['type']);
        self::assertCount(1, $backgrounds);
        $text = array_values(array_filter($operations, static fn (array $operation) => 'text' === $operation['type']));
        self::assertSame(3.0, $text[0]['x']);
    }

    public function testDisplayListIsDeterministicAndContainsNoDocumentHandles()
    {
        $renderer = new HtmlRenderer();
        $context = new RenderingContext(120, 80);
        $html = '<p style="background-color: blue">Hello</p>';
        $first = $renderer->render($html, $context)->getDisplayList()->getOperations();
        self::assertSame($first, $renderer->render($html, $context)->getDisplayList()->getOperations());
        self::assertIsString(json_encode($first, \JSON_THROW_ON_ERROR));
    }
}
