<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Internal\SvgMirror;
use Symfony\Bridge\TuiHtml\Style\VectorImage;
use Symfony\Bridge\TuiHtml\Widget\SvgWidget;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\Tui\Widget\ContainerWidget;

final class SvgMirrorTest extends TestCase
{
    public function testUnchangedSubtreesRetainNativeIdentityAndDetachedWidgetsReleaseTheirMirrors()
    {
        $source = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewBox="0 0 10 10"><g><rect width="10" height="10"/></g></svg>');
        $svg = $source->querySelector('svg');
        $widget = new SvgWidget(VectorImage::fromElement($svg));
        $original = $source->saveHtml();
        $root = new ContainerWidget();
        $root->add($widget);
        $mirror = new SvgMirror();
        $mirror->register($widget, $svg);
        $document = \Dom\HTMLDocument::createEmpty();
        $element = $document->createElementNS('http://www.w3.org/2000/svg', 'svg');
        $document->appendChild($element);
        self::assertTrue($mirror->scan($root));
        self::assertTrue($mirror->sync([spl_object_id($widget) => $element]));
        $rect = $element->querySelector('rect');
        self::assertNotSame($svg->querySelector('rect'), $rect);
        self::assertSame($original, $source->saveHtml());
        self::assertFalse($mirror->scan($root));
        self::assertFalse($mirror->sync([spl_object_id($widget) => $element]));
        self::assertSame($rect, $element->querySelector('rect'));
        $svg->querySelector('rect')->setAttribute('fill', 'red');
        self::assertTrue($mirror->scan($root));
        self::assertTrue($mirror->sync([spl_object_id($widget) => $element]));
        self::assertNotSame($rect, $element->querySelector('rect'));
        self::assertSame('red', $element->querySelector('rect')->getAttribute('fill'));
        self::assertTrue($mirror->scan(new ContainerWidget()));
        self::assertFalse($mirror->contains($widget));
        self::assertNull($element->firstChild);
    }

    public function testSnapshotDepthAndByteBudgetsAreCheckedBeforeImport()
    {
        $document = \Dom\XMLDocument::createEmpty();
        $root = $document->createElementNS('http://www.w3.org/2000/svg', 'svg');
        $document->appendChild($root);
        $node = $root;
        for ($depth = 0; $depth < 513; ++$depth) {
            $node->appendChild($next = $document->createElementNS('http://www.w3.org/2000/svg', 'g'));
            $node = $next;
        }
        try {
            SvgMirror::fingerprint($root);
            self::fail('A deeply nested snapshot must be rejected.');
        } catch (\LengthException $exception) {
            self::assertStringContainsString('512 levels', $exception->getMessage());
        }
        $root->replaceChildren();
        $root->setAttribute('data-large', str_repeat('a', 8 * 1024 * 1024));
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('8 MiB');
        SvgMirror::fingerprint($root);
    }

    public function testSnapshotNodeBudgetRejectsAWideTreeWithoutImportingIt()
    {
        $document = \Dom\XMLDocument::createEmpty();
        $root = $document->createElementNS('http://www.w3.org/2000/svg', 'svg');
        $document->appendChild($root);
        for ($index = 0; $index < 20000; ++$index) {
            $root->appendChild($document->createElementNS('http://www.w3.org/2000/svg', 'rect'));
        }
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('20000 nodes');
        SvgMirror::fingerprint($root);
    }

    public function testDetachmentRevokesCapturedStylesBeforeTheNextScan()
    {
        $document = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>');
        $svg = $document->documentElement;
        $widget = new SvgWidget(VectorImage::fromElement($svg));
        $root = new ContainerWidget();
        $root->add($widget);
        $registry = new SvgMirror();
        $registry->register($widget, $svg);
        $registry->scan($root);
        $mirror = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg"/>');
        $registry->sync([spl_object_id($widget) => $mirror->documentElement]);
        $styles = new \SplObjectStorage();
        $styles[$mirror->documentElement->firstElementChild] = ['fill' => 'red'];
        $registry->capture($styles, new StyleResolver());
        self::assertSame(['fill' => 'red'], $registry->get($svg->firstElementChild));
        $root->remove($widget);
        self::assertFalse($registry->contains($widget));
        self::assertNull($registry->get($svg->firstElementChild));
    }
}
