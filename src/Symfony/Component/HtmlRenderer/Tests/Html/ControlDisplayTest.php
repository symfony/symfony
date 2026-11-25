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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class ControlDisplayTest extends TestCase
{
    public function testCompletionSuffixPaintsWithoutChangingValueTextPositions()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>input::suggestion{color:red}</style><input value="Fo">');
        $input = $document->querySelector('input');
        $result = HtmlRenderer::compute($document, new RenderingContext(400, 200), [[$input, ['value' => 'Fo', 'completion' => 'o']]]);
        $texts = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']));
        self::assertSame(['Fo', 'o'], array_column($texts, 'text'));
        self::assertSame('red', $texts[1]['color']);
        self::assertSame('Fo', $input->getAttribute('value'));
        self::assertSame(2, $result->getTextPosition($input, $texts[1]['x'] + 4, $texts[1]['y'] + 4));
    }

    public function testEditableFallbackControlsDisplayExplicitPlaceholders()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>input:placeholder-shown{color:red}</style><input type="file" placeholder="Pick"><input type="color" placeholder="Color">');
        $inputs = $document->querySelectorAll('input');
        $result = HtmlRenderer::compute($document, new RenderingContext(400, 200), [[$inputs[1], ['value' => '']]]);
        $texts = array_values(array_filter(array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']), 'text'), static fn (string $text) => '' !== $text));
        self::assertSame(['Pick', 'Color'], $texts);
        self::assertSame('red', $result->getComputedStyle($inputs[0])['color']);
        self::assertSame('red', $result->getComputedStyle($inputs[1])['color']);
    }

    public function testTextareaValueAttributeDoesNotSpoofPlaceholderOrValidation()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>textarea:invalid{color:red}textarea:placeholder-shown{background-color:blue}</style><textarea value="Wrong" placeholder="Name" required></textarea>', new RenderingContext(400, 200));
        $style = $result->getComputedStyle($result->getDocument()->querySelector('textarea'));
        self::assertSame('red', $style['color']);
        self::assertSame('blue', $style['background-color']);
    }

    public function testInitialValuesPreserveNativeDefaultsAndStrictColorSwatches()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><textarea value="Wrong">

Actual</textarea><input type="file" value="ignored"><input type="color" value=" #AABBCC ">');
        self::assertSame("\nActual", \Symfony\Component\HtmlRenderer\Form\ControlValue::initial($document->querySelector('textarea')));
        self::assertSame('', \Symfony\Component\HtmlRenderer\Form\ControlValue::initial($document->querySelector('[type=file]')));
        self::assertSame('#aabbcc', \Symfony\Component\HtmlRenderer\Form\ControlValue::initial($document->querySelector('[type=color]')));
        self::assertSame('#aabbcc', \Symfony\Component\HtmlRenderer\Form\ControlValue::colorSwatch('#AABBCC'));
        self::assertNull(\Symfony\Component\HtmlRenderer\Form\ControlValue::colorSwatch("#aabbcc\n"));
    }

    public function testLabelsAndTextareaContentsAreDistinctFromSubmittedValues()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><textarea value="Wrong">Actual</textarea><button value="sent"><span hidden>Wrong</span>Label</button><input type="submit"><input type="reset">', new RenderingContext(800, 400));
        $texts = array_values(array_filter(array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']), 'text'), static fn (string $text) => '' !== $text));
        self::assertSame(['Actual', 'Label', 'Submit', 'Reset'],$texts);
    }
}
