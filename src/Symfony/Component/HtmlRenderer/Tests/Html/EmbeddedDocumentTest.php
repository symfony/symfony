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
use Symfony\Component\HtmlRenderer\Html\EmbeddedDocument;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

final class EmbeddedDocumentTest extends TestCase
{
    public function testSrcdocIncludingEmptyWinsWithoutLoading()
    {
        $document = \Dom\HTMLDocument::createFromString('<iframe srcdoc="&lt;p&gt;Inside&lt;/p&gt;" src="other.html"></iframe><iframe srcdoc="" src="other.html"></iframe>', \LIBXML_NOERROR);
        $load = static function (): never { throw new \LogicException('src must not be loaded.'); };
        self::assertSame('<p>Inside</p>', EmbeddedDocument::read($document->querySelector('iframe'), $load));
        self::assertSame('', EmbeddedDocument::read($document->querySelectorAll('iframe')->item(1), $load));
    }

    public function testExternalDocumentUsesOnlySuppliedLoaderAndRequiresUtf8()
    {
        $document = \Dom\HTMLDocument::createFromString('<iframe src=" help.html "></iframe>', \LIBXML_NOERROR);
        $frame = $document->querySelector('iframe');
        $sources = [];
        self::assertSame('<p>Help</p>', EmbeddedDocument::read($frame, static function (string $source) use (&$sources): string {
            $sources[] = $source;

            return '<p>Help</p>';
        }));
        self::assertSame(['help.html'], $sources);
        self::assertNull(EmbeddedDocument::read($frame, static fn () => "\xff"));
        self::assertNull(EmbeddedDocument::read($frame, static fn () => null));
    }

    public function testBlankAndOtherElementsDoNotLoad()
    {
        $document = \Dom\HTMLDocument::createFromString('<iframe></iframe><iframe src=" ABOUT:BLANK "></iframe><img src="image.html">', \LIBXML_NOERROR);
        $load = static function (): never { throw new \LogicException('No resource should be loaded.'); };
        foreach ($document->querySelectorAll('iframe,img') as $element) {
            self::assertNull(EmbeddedDocument::read($element, $load));
        }
    }

    public function testDataDocumentsUseExistingBoundedResourcePolicy()
    {
        $document = \Dom\HTMLDocument::createFromString('<iframe src="data:text/html,%3Cp%3EInline%3C%2Fp%3E"></iframe>', \LIBXML_NOERROR);
        $frame = $document->querySelector('iframe');
        self::assertSame('<p>Inline</p>', EmbeddedDocument::read($frame, new ResourceLoader()->load(...)));
        self::assertNull(EmbeddedDocument::read($frame, new ResourceLoader(null, 4)->load(...)));
        $frame->setAttribute('src', 'https://example.com/private');
        self::assertNull(EmbeddedDocument::read($frame, new ResourceLoader()->load(...)));
    }
}
