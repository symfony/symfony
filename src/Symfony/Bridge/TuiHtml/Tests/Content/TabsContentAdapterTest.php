<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Content;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Content\TabsContentAdapter;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\Tui\Widget\TabItem;
use Symfony\Component\Tui\Widget\TabsWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class TabsContentAdapterTest extends TestCase
{
    public function testSecondPassMutationStillFailsTheNativeRevisionGuard()
    {
        $widget = new class([new TabItem('first', 'First', new TextWidget('Body'))]) extends TabsWidget {
            public function renderWithContent(RenderContext $context, \Closure $renderContent): array
            {
                $this->invalidate();

                return parent::renderWithContent($context, $renderContent);
            }
        };
        $adapter = new TabsContentAdapter($widget, [], new TerminalTextMeasurer(), new FontRegistry(), new MediaContext(), static fn () => ['lines' => ['Body']]);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Native content changed after preparation');
        $adapter->layout(160, 160);
    }

    public function testDisposalReleasesPreparedBodyClosureAndRejectsReuse()
    {
        $owner = new \stdClass();
        $owner->text = 'Body';
        $reference = \WeakReference::create($owner);
        $widget = new TabsWidget([new TabItem('first', 'First', new TextWidget('Body'))]);
        $adapter = new TabsContentAdapter($widget, [], new TerminalTextMeasurer(), new FontRegistry(), new MediaContext(), static fn () => ['lines' => [$owner->text]]);
        $adapter->layout(160, 160);
        unset($owner);
        self::assertNotNull($reference->get());
        $adapter->dispose();
        self::assertNull($reference->get());
        $this->expectException(\LogicException::class);
        $adapter->layout(160, 160);
    }
}
