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

class DocumentIdsTest extends TestCase
{
    public function testParserUnmarkedSvgIdsParticipateInNativeLookupAndNavigation()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>svg:target{color:red}</style><svg id="logo" width="20" height="10"></svg>');
        $svg = $document->querySelector('svg');
        $svg->setIdAttribute('id', false);
        $source = $document->saveHtml();
        $session = new HtmlRenderer()->createSession($document, new RenderingContext(100, 100));
        self::assertSame($svg, $session->getDocument()->getElementById('logo'));
        self::assertTrue($session->navigate('#logo'));
        self::assertSame($svg, $session->getTarget());
        self::assertSame('red', $session->render()->getComputedStyle($svg)['color']);
        self::assertSame($source, $document->saveHtml());
    }

    public function testInsertedUnmarkedIdsCanBeNavigatedBeforeAnExplicitRender()
    {
        $session = new HtmlRenderer()->createSession('<main></main>', new RenderingContext(100, 100));
        $session->render();
        $document = $session->getDocument();
        $svg = $document->createElementNS('http://www.w3.org/2000/svg', 'svg');
        $svg->setAttribute('id', 'a"[é]#');
        $svg->setAttribute('width', '20');
        $svg->setAttribute('height', '10');
        $document->querySelector('main')->appendChild($svg);
        $svg->setIdAttribute('id', false);
        $source = $document->saveHtml();
        self::assertTrue($session->navigate('#'.rawurlencode($svg->id)));
        self::assertSame($svg, $session->getTarget());
        self::assertSame($svg, $document->getElementById($svg->id));
        self::assertSame($source, $document->saveHtml());
    }

    public function testRenderingRegistersUnmarkedIdsAfterDocumentMutation()
    {
        $session = new HtmlRenderer()->createSession('<main></main>', new RenderingContext(100, 100));
        $session->render();
        $document = $session->getDocument();
        $svg = $document->createElementNS('http://www.w3.org/2000/svg', 'svg');
        $svg->setAttribute('id', 'inserted');
        $document->querySelector('main')->appendChild($svg);
        $svg->setIdAttribute('id', false);
        $source = $document->saveHtml();
        $session->render();
        self::assertSame($svg, $document->getElementById('inserted'));
        self::assertSame($source, $document->saveHtml());
    }

    public function testEmptyIdsDoNotBecomeNativeLookupTargets()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><svg id=""></svg>');
        $svg = $document->querySelector('svg');
        $svg->setIdAttribute('id', false);
        $source = $document->saveHtml();
        $session = new HtmlRenderer()->createSession($document, new RenderingContext(100, 100));
        self::assertNull($document->getElementById(''));
        self::assertFalse($session->navigate('#'));
        self::assertSame($source, $document->saveHtml());
    }
}
