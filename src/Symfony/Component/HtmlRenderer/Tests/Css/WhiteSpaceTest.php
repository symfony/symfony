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
use Symfony\Component\HtmlRenderer\Css\WhiteSpace;

class WhiteSpaceTest extends TestCase
{
    public function testSharedLegacyGrammarAndComponentResolution()
    {
        self::assertSame('pre', WhiteSpace::parse('preserve nowrap'));
        self::assertSame(['preserve', 'nowrap'], WhiteSpace::components('nowrap preserve'));
        self::assertSame('pre-line', WhiteSpace::resolve(['white-space-collapse' => 'preserve-breaks']));
        self::assertNull(WhiteSpace::parse('preserve collapse'));
    }

    public function testLonghandsCompeteWithShorthandAndInherit()
    {
        foreach (['white-space:pre;text-wrap-mode:wrap' => 'pre-wrap', 'text-wrap-mode:wrap;white-space:pre' => 'pre', 'white-space:normal;white-space-collapse:preserve' => 'pre-wrap'] as $css => $expected) {
            $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p style="'.$css.'"><span>Text</span></p>');
            $styles = new StyleResolver()->resolve($document, 400, 200);
            self::assertSame($expected, $styles[$document->querySelector('p')]['white-space']);
            self::assertSame($expected, $styles[$document->querySelector('span')]['white-space']);
        }
    }

    public function testPseudoElementLonghandsProduceUsedWhitespaceMode()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p::before{content:"A B";white-space:normal;white-space-collapse:preserve;text-wrap-mode:nowrap}</style><p>Text</p>');
        $resolver = new StyleResolver();
        $resolver->resolve($document, 400, 200);
        self::assertSame('pre', $resolver->getPseudoStyle($document->querySelector('p'), 'before')['white-space']);
    }
}
