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
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

class ColorSchemeTest extends TestCase
{
    public function testUsedSchemeInheritsAndResolvesFunctionsAfterCascadeWithAliases()
    {
        foreach ([[], ['custom-ink' => '#00ff00']] as $aliases) {
            foreach (['light', 'dark'] as $preference) {
                $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>main{color-scheme:dark;color:light-dark(red,blue)}span{background-color:light-dark(red,blue)}#both{color-scheme:light dark;color:light-dark(red,blue)}</style><main><span>Text</span></main><p id="both">Both</p>');
                $resolver = new StyleResolver(new ResourceLoader(), new FontStyle(), new MonospaceTextMeasurer(), ['prefers-color-scheme' => $preference], '', $aliases);
                $styles = $resolver->resolve($document, 400, 200);
                self::assertSame('#0000ff', $styles[$document->querySelector('main')]['color']);
                self::assertSame('#0000ff', $styles[$document->querySelector('span')]['background-color']);
                self::assertSame('dark', $styles[$document->querySelector('span')]['color-scheme']);
                self::assertSame('dark' === $preference ? '#0000ff' : '#ff0000', $styles[$document->getElementById('both')]['color']);
            }
        }
    }

    public function testMetaSchemeHintFollowsAuthorCascadeAndInitialSystemColor()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><meta name="color-scheme" content="dark"><p>Text</p>');
        $resolver = new StyleResolver();
        self::assertSame('#ffffff', $resolver->resolve($document, 400, 200)[$document->querySelector('p')]['color']);
        $document->documentElement->setAttribute('style', 'color-scheme:light');
        self::assertSame('#000000', $resolver->resolve($document, 400, 200)[$document->querySelector('p')]['color']);
    }

    public function testAnimatedAliasEndpointsResolveUnderUsedScheme()
    {
        $context = new RenderingContext(400, 200, new MonospaceTextMeasurer(), new ResourceLoader(), null, new FontStyle(), [], '', ['custom-ink' => '#00ff00']);
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>@keyframes shade{from{color:light-dark(red,blue)}to{color:custom-ink}}p{color-scheme:dark;animation:shade 2s linear forwards}</style><p>Text</p>', $context);
        $paragraph = $session->getDocument()->querySelector('p');
        $session->render();
        $session->advance(1);
        self::assertSame('#008080', $session->render()->getComputedStyle($paragraph)['color']);
    }

    public function testAnimatedColorEndpointsUseElementSchemeAndAliasContext()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>@keyframes shade{from{color:light-dark(red,blue)}to{color:light-dark(white,black)}}p{color-scheme:dark;animation:shade 2s linear forwards}</style><p>Text</p>', new RenderingContext(400, 200));
        $paragraph = $session->getDocument()->querySelector('p');
        $session->render();
        $session->advance(1);
        self::assertSame('#000080',$session->render()->getComputedStyle($paragraph)['color']);
    }
}
