<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class ControlPseudoClassTest extends TestCase
{
    public function testPlaceholderAndDirtyValidityFollowSessionValuesAndReset()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>input:placeholder-shown{color:red}input:user-invalid{background-color:blue}input:user-valid{background-color:green}</style><form><input placeholder="Name" required></form>', new RenderingContext(400, 200));
        $input = $session->getDocument()->querySelector('input');
        $form = $session->getDocument()->querySelector('form');
        self::assertSame('red', $session->render()->getComputedStyle($input)['color']);
        self::assertArrayNotHasKey('background-color', $session->render()->getComputedStyle($input));
        $session->submit($form);
        self::assertSame('blue', $session->render()->getComputedStyle($input)['background-color']);
        $session->focus($input);
        $session->input('Name');
        self::assertSame('green', $session->render()->getComputedStyle($input)['background-color']);
        self::assertSame('#000000', $session->render()->getComputedStyle($input)['color']);
        $session->reset($form);
        self::assertArrayNotHasKey('background-color', $session->render()->getComputedStyle($input));
        self::assertSame('red', $session->render()->getComputedStyle($input)['color']);
    }

    public function testPlaceholderPaintingDoesNotChangeEditableValueOrTextPosition()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>input::placeholder{color:red}</style><input placeholder="Name">', new RenderingContext(400, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        $result = $session->render();
        $texts = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type'] && 'Name' === $operation['text']));
        self::assertCount(1, $texts);
        self::assertSame('red', $texts[0]['color']);
        self::assertSame('', $session->getValue($input));
        $bounds = $result->getBounds($input)[0];
        self::assertSame(0, $result->getTextPosition($input, $bounds->x + 30, $bounds->y + 4));
    }

    public function testAggregateValidityIncludesExternalOwnersAndExcludesBarredControls()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>form:invalid,fieldset:invalid{background-color:blue}input:invalid{background-color:red}</style><form id="f"><fieldset id="group"><input required readonly><input required disabled><input required type="hidden"></fieldset></form><input form="f" required>');
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 400, 200);
        self::assertSame('blue', $styles[$document->getElementById('f')]['background-color']);
        self::assertArrayNotHasKey('background-color', $styles[$document->getElementById('group')]);
        foreach ($document->querySelectorAll('#group input') as $input) {
            self::assertArrayNotHasKey('background-color', $styles[$input]);
        }
    }
}
