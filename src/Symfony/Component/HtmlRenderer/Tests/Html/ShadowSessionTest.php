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
use Symfony\Component\HtmlRenderer\RenderingResult;

final class ShadowSessionTest extends TestCase
{
    public function testSourceAndFragmentMutationsRetainStableOwnersAndInvalidateRendering()
    {
        $session = (new HtmlRenderer())->createSession('<style>user-card{display:block}</style><user-card><template shadowrootmode="open"><p id="inside">Inside</p></template><span>Unassigned</span></user-card>', new RenderingContext(400, 300));
        $first = $session->render();
        $inside = $this->textOwner($first, 'Inside');
        self::assertNotNull($inside);
        self::assertFalse($inside->isConnected);
        self::assertSame($first, $session->render());
        self::assertNull($session->getDocument()->getElementById('inside'));
        $session->getDocument()->body->appendChild($session->getDocument()->createElement('aside'));
        $second = $session->render();
        self::assertNotSame($first, $second);
        self::assertSame($inside, $this->textOwner($second, 'Inside'));
        $inside->textContent = 'Changed';
        $third = $session->render();
        self::assertNotSame($second, $third);
        self::assertSame($inside, $this->textOwner($third, 'Changed'));
        self::assertSame($third, $session->render());
        self::assertStringContainsString('Inside', $session->getDocument()->querySelector('template')->innerHTML);
        $session->getDocument()->querySelector('template')->innerHTML = '<p id="inside">Replacement</p>';
        $fourth = $session->render();
        self::assertNotSame($inside, $this->textOwner($fourth, 'Replacement'));
        self::assertSame([], $fourth->getBounds($inside));
    }

    public function testUnchangedFramesAndExplicitInvalidationDoNotReconstructTheView()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><p>Inside</p></template></user-card>', new RenderingContext(400, 300));
        $first = $session->render();
        $property = new \ReflectionProperty($session, 'shadowView');
        $document = $property->getValue($session)['document'];
        self::assertSame($first, $session->render());
        self::assertSame($document, $property->getValue($session)['document']);
        $session->invalidate();
        self::assertNotSame($first, $session->render());
        self::assertSame($document, $property->getValue($session)['document']);
    }

    public function testReplacingAViewReleasesItsConnectedSvgResourceEntries()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><svg width="8" height="8"><rect width="8" height="8" fill="red"/></svg></template></user-card>', new RenderingContext(400, 300));
        $resources = (new \ReflectionProperty($session, 'resources'))->getValue($session);
        $views = new \ReflectionProperty($resources, 'svgViews');
        for ($index = 0; $index < 5; ++$index) {
            $session->getDocument()->body->setAttribute('data-revision', (string) $index);
            $result = $session->render();
            self::assertContains('svg', array_column($result->getDisplayList()->getOperations(), 'type'));
            self::assertCount(1, $views->getValue($resources));
        }
    }

    public function testRemovingTheLastRootReturnsToTheSourceDocumentFastPath()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><p>Inside</p></template></user-card>', new RenderingContext(400, 300));
        $inside = $this->textOwner($session->render(), 'Inside');
        $session->getDocument()->querySelector('user-card')->remove();
        $result = $session->render();
        self::assertSame([], $result->getBounds($inside));
        self::assertNull((new \ReflectionProperty($session, 'shadowView'))->getValue($session));
        self::assertNull((new \ReflectionProperty($session, 'shadowRoots'))->getValue($session));
        self::assertSame($result, $session->render());
    }

    public function testMembershipRefreshCanRemoveTheLastShadowView()
    {
        $session = (new HtmlRenderer())->createSession('<user-card id="host"><template shadowrootmode="open"><p>Inside</p></template></user-card>', new RenderingContext(200, 100));
        $session->render();
        $document = $session->getDocument();
        $host = $document->getElementById('host');
        $host->remove();
        $button = $document->createElement('button');
        $button->textContent = 'New';
        $document->body->appendChild($button);
        self::assertTrue($session->containsElement($button));
        self::assertFalse($session->containsElement($host));
        self::assertNotEmpty($session->render()->getBounds($button));
    }

    private function textOwner(RenderingResult $result, string $text): ?\Dom\Element
    {
        foreach ($result->getDisplayList()->getOperations() as $operation) {
            if ('text' === $operation['type'] && $text === $operation['text']) {
                return $result->hitTest($operation['x'] + $operation['width'] / 2, $operation['y'] + $operation['height'] / 2);
            }
        }

        return null;
    }
}
