<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\RenderSession;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;

final class ShadowDocumentWidgetTest extends TestCase
{
    public function testOriginalShadowFixtureRendersInAllExistingViewportPolicies()
    {
        $html = file_get_contents(\dirname(__DIR__).'/Fixtures/Compat/html-shadow-root.html');
        foreach ([[80, Density::Regular], [40, Density::Regular], [80, Density::Condensed]] as [$columns, $density]) {
            $session = (new HtmlRenderer())->createSession($html, new RenderingContext($columns * 8, 960));
            $widget = (new DocumentWidget($session))->setDensity($density);
            $text = AnsiUtils::stripAnsiCodes(implode("\n", $widget->render(new RenderContext($columns, 60))));
            self::assertStringContainsString('Ada Lovelace', $text);
            self::assertStringContainsString('Anonymous', $text);
            self::assertStringContainsString('Status:', $text);
            self::assertStringContainsString('online', $text);
            $result = $session->render();
            $widget->render(new RenderContext($columns, 60));
            self::assertSame($result, $session->render());
        }
    }

    public function testNativeShadowControlsReceiveTerminalPoliciesWithoutTouchingUnslottedNodes()
    {
        $html = '<user-card><template shadowrootmode="open"><select id="select"><option>Banana</option></select><progress value="3" max="4"></progress><input type="file"></template><input id="inactive" type="range"><p>Unslotted</p></user-card>';
        $session = (new HtmlRenderer())->createSession($html, new RenderingContext(640, 320));
        $widget = new DocumentWidget($session);
        $text = AnsiUtils::stripAnsiCodes(implode("\n", $widget->render(new RenderContext(80, 20))));
        self::assertStringContainsString('[ Banana ▾ ]', $text);
        self::assertStringContainsString('75%', $text);
        self::assertStringContainsString('Path to a file', $text);
        self::assertStringNotContainsString('Unslotted', $text);
        self::assertNotNull($session->getDocument()->getElementById('inactive'));
        self::assertSame([], $session->render()->getBounds($session->getDocument()->getElementById('inactive')));
    }

    public function testDensityCrossesHostsAndFlowsIntoShadowIframes()
    {
        $html = '<user-card data-density="condensed"><template shadowrootmode="open"><style>:condensed{color:red}:regular{color:blue}</style><p id="compact">Compact</p><section data-density="regular"><p id="regular">Regular</p></section><iframe id="frame" srcdoc="&lt;style&gt;:condensed{color:red}:regular{color:blue}&lt;/style&gt;&lt;p id=child&gt;Child&lt;/p&gt;"></iframe></template></user-card>';
        $session = (new HtmlRenderer())->createSession($html, new RenderingContext(640, 480));
        $widget = new DocumentWidget($session);
        $widget->render(new RenderContext(80, 30));
        $result = $session->render();
        self::assertSame('red', $result->getComputedStyle($this->element($session, 'compact'))['color']);
        self::assertSame('blue', $result->getComputedStyle($this->element($session, 'regular'))['color']);
        $child = $session->getEmbeddedSession($this->element($session, 'frame'));
        self::assertNotNull($child);
        self::assertSame('red', $child->render()->getComputedStyle($child->getDocument()->getElementById('child'))['color']);
    }

    public function testPolicyCallbacksMayDetachTheCurrentAndNextControls()
    {
        $session = (new HtmlRenderer())->createSession('<progress id="current" value="1" max="4"></progress><progress id="next" value="1" max="4"></progress>', new RenderingContext(640, 320));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(80, 20);
        $widget->render($context);
        $current = $session->getDocument()->getElementById('current');
        $next = $session->getDocument()->getElementById('next');
        $session->onInvalidation(static function () use ($current, $next): void {
            $current->remove();
            $next->remove();
        });
        $current->setAttribute('value', '2');
        $widget->render($context);
        self::assertFalse($current->isConnected);
        self::assertFalse($next->isConnected);
        self::assertSame([], $session->render()->getBounds($current));
        self::assertSame([], $session->render()->getBounds($next));
    }

    public function testDensityCallbacksMayDetachTheirCurrentComposedHost()
    {
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><p>Inside</p></template></user-card>', new RenderingContext(640, 320));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(80, 20);
        $widget->render($context);
        $host = $session->getDocument()->querySelector('user-card');
        $session->onInvalidation(static function () use ($host): void { $host->remove(); });
        $host->setAttribute('data-density', 'condensed');
        $widget->render($context);
        self::assertFalse($host->isConnected);
        self::assertSame([], $session->render()->getBounds($host));
    }

    public function testUserInvalidArgumentExceptionsFromPolicyCallbacksPropagate()
    {
        $session = (new HtmlRenderer())->createSession('<progress value="1" max="4"></progress>', new RenderingContext(640, 320));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(80, 20);
        $widget->render($context);
        $failure = new \InvalidArgumentException('Observer failure');
        $session->onInvalidation(static function () use ($failure): void { throw $failure; });
        $session->getDocument()->querySelector('progress')->setAttribute('value', '2');
        $this->expectExceptionObject($failure);
        $widget->render($context);
    }

    private function element(RenderSession $session, string $id): \Dom\Element
    {
        foreach ($session->getComposedElements() as [$element]) {
            if ($element->getAttribute('id') === $id) {
                return $element;
            }
        }
        self::fail('Missing composed element '.$id);
    }
}
