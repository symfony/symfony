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
use Symfony\Component\HtmlRenderer\Form\NumericControl;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class NumericAndSelectTest extends TestCase
{
    public function testPickerGeneratesNativeOptionsInItsOwnTypedLayer()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>select::picker(select){background-color:red}</style><select><option>PHP</option><option>Rust</option></select>');
        $select = $document->querySelector('select');
        $result = HtmlRenderer::compute($document, new RenderingContext(800, 400), [[$select, ['picker-open' => true, 'picker-layer' => 3]]]);
        $picker = null;
        $visit = static function ($fragment) use (&$visit, &$picker) {
            if ('picker' === $fragment->pseudoElement) {
                $picker = $fragment;
            }
            foreach ($fragment->children as $child) {
                $visit($child);
            }
        };
        $visit($result->getLayout());
        self::assertNotNull($picker);
        self::assertSame(3, $picker->topLayer);
        self::assertSame($select, $picker->node);
        self::assertSame('red', $picker->style['background-color']);
        self::assertNotEmpty($result->getBounds($document->querySelectorAll('option')[1]));
    }

    public function testNumericModelsAndBoxPrimitivesRemainIndependentOfDevices()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>progress,meter,input{width:200px;height:20px}</style><progress value="3" max="4"></progress><meter value=".9" low=".3" high=".7" optimum=".1"></meter><input type="range" value="25">', new RenderingContext(800, 400));
        $document = $session->getDocument();
        self::assertSame(.75, NumericControl::progress($document->querySelector('progress'))['fraction']);
        self::assertSame('even-less-good', NumericControl::meter($document->querySelector('meter'))['region']);
        self::assertSame(.25, NumericControl::range($document->querySelector('input'))['fraction']);
        $result = $session->render();
        $roles = [];
        $visit = static function ($fragment) use (&$visit, &$roles) {
            if (null !== $fragment->pseudoElement) {
                $roles[$fragment->node?->localName ?? ''][$fragment->pseudoElement][] = $fragment;
            }
            foreach ($fragment->children as $child) {
                $visit($child);
            }
        };
        $visit($result->getLayout());
        self::assertSame(150.0, $roles['progress']['bar-fill'][0]->bounds->width);
        self::assertNotEmpty($roles['input']['thumb']);
        self::assertNotEmpty(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'rectangle' === $operation['type']));
    }

    public function testListboxPaintsAllOptionsWithNativeIdentityAndSelectedStyles()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>option:checked{color:red}</style><select size="3"><option selected>PHP</option><option>Rust</option><option>Go</option></select>', new RenderingContext(800, 400));
        $result = $session->render();
        $options = $session->getDocument()->querySelectorAll('option');
        $texts = array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']), 'text');
        self::assertSame(['PHP', 'Rust', 'Go'], $texts);
        self::assertNotEmpty($result->getBounds($options[1]));
        self::assertSame('red', $result->getComputedStyle($options[0])['color']);
        $session->activate($options[1]);
        self::assertSame('Rust', $session->getValue($session->getDocument()->querySelector('select')));
        self::assertFalse($options[1]->hasAttribute('selected'));
    }
}
