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
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

class TextSpacingStyleTest extends TestCase
{
    public function testRelativeSpacingIsComputedBeforeInheritance()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>p{font-size:20px;letter-spacing:.1em;word-spacing:.2em}span{font-size:10px}.own{letter-spacing:.1em;word-spacing:normal}p::before{content:"X";font-size:10px;letter-spacing:.1em}</style><p><span id="inherited">x</span><span class="own">y</span></p>');
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 400, 200);
        self::assertSame('2px', $styles[$document->querySelector('p')]['letter-spacing']);
        self::assertSame('2px', $styles[$document->querySelector('#inherited')]['letter-spacing']);
        self::assertSame('4px', $styles[$document->querySelector('#inherited')]['word-spacing']);
        self::assertSame('1px', $styles[$document->querySelector('.own')]['letter-spacing']);
        self::assertSame('normal', $styles[$document->querySelector('.own')]['word-spacing']);
        self::assertSame('1px', $resolver->getPseudoStyle($document->querySelector('p'), 'before')['letter-spacing']);
    }

    public function testAbsentDefaultsStayAbsentAndSignedLengthsRemainSigned()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><p>x</p><div style="font-size:20px;letter-spacing:-.2em;word-spacing:2ch">y</div>');
        $styles = new StyleResolver()->resolve($document, 400, 200);
        self::assertArrayNotHasKey('letter-spacing', $styles[$document->querySelector('p')]);
        self::assertArrayNotHasKey('word-spacing', $styles[$document->querySelector('p')]);
        self::assertSame('-4px', $styles[$document->querySelector('div')]['letter-spacing']);
        self::assertSame('20px', $styles[$document->querySelector('div')]['word-spacing']);
    }

    public function testDefaultTypographySpacingDoesNotChangeChUnitsAndInitialResetsIt()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>@media(min-width:10ch){p{color:red}}span{letter-spacing:initial;word-spacing:initial}</style><p><span>x</span></p>');
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle('sans-serif', 16, 19.2, 400, 'ltr', 'normal', 100, 3));
        $styles = $resolver->resolve($document, 81, 200);
        self::assertSame('red', $styles[$document->querySelector('p')]['color']);
        self::assertSame('100px', $styles[$document->querySelector('p')]['letter-spacing']);
        self::assertSame('3px', $styles[$document->querySelector('p')]['word-spacing']);
        self::assertSame('normal', $styles[$document->querySelector('span')]['letter-spacing']);
        self::assertSame('normal', $styles[$document->querySelector('span')]['word-spacing']);
    }
}
