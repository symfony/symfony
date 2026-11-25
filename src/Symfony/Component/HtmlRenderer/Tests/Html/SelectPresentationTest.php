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
use Symfony\Component\HtmlRenderer\Form\SelectOptions;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class SelectPresentationTest extends TestCase
{
    public function testPickerIconHasNoDeviceDefaultAndUsesAuthoredContent()
    {
        $session = (new HtmlRenderer())->createSession('<select><option>Banana</option></select>', new RenderingContext(400, 200));
        $texts = static fn ($result) => array_column(array_filter($result->getDisplayList()->getOperations(), static fn ($operation) => 'text' === $operation['type']), 'text');
        self::assertSame(['Banana'], array_values($texts($session->render())));
        $style = $session->getDocument()->createElement('style');
        $style->textContent = 'select::picker-icon{content:"Icon";color:blue}';
        $session->getDocument()->head->append($style);
        self::assertSame(['Banana', 'Icon'], array_values($texts($session->render())));
    }

    public function testListBoxClassificationPreservesMultipleAndSizeSemantics()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><select></select><select size="1"></select><select size="invalid"></select><select size="2"></select><select multiple></select>');
        self::assertSame([false, false, false, true, true], array_map(SelectOptions::isListBox(...), iterator_to_array($document->querySelectorAll('select'))));
    }
}
