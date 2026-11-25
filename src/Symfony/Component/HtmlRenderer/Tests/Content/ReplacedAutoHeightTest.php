<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Content;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Content\IntrinsicSize;
use Symfony\Component\HtmlRenderer\Content\ReplacedContentInterface;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\RenderingContext;

class ReplacedAutoHeightTest extends TestCase
{
    public function testAutoHeightCanExceedItsContainingRowButExplicitHeightAndMaximumStillConstrainIt(): void
    {
        foreach (['' => [60.0, 60.0], 'height:24px' => [24.0, 24.0], 'max-height:32px' => [32.0, 32.0]] as $style => [$height, $room]) {
            $provider = new class implements ReplacedContentInterface {
                public float $room = 0;

                public function getIntrinsicSize(): IntrinsicSize
                {
                    return new IntrinsicSize(10, 20, 60);
                }

                public function layout(float $width, float $availableHeight): ContentLayout
                {
                    $this->room = $availableHeight;

                    return new ContentLayout(min(60.0, $availableHeight), new DisplayList());
                }
            };
            $context = new RenderingContext(200, 100, null, null, null, null, [], '', [], null, static fn (\Dom\Element $node) => 'app-native' === $node->localName ? $provider : null);
            $session = new HtmlRenderer()->createSession('<div style="height:16px"><app-native style="display:block;width:20px;'.$style.'"></app-native></div>', $context);
            $result = $session->render();
            self::assertSame($height, $result->getBounds($session->getDocument()->querySelector('app-native'))[0]->height, $style);
            self::assertSame($room, $provider->room, $style);
        }
    }

    public function testWidthDependentContentCanReturnItsOwnWrappedHeight(): void
    {
        $provider = new class implements ReplacedContentInterface {
            public function getIntrinsicSize(): IntrinsicSize
            {
                return new IntrinsicSize(10, 20, 60);
            }

            public function layout(float $width, float $availableHeight): ContentLayout
            {
                return new ContentLayout(60 * 20 / $width, new DisplayList());
            }
        };
        $context = new RenderingContext(200, 100, null, null, null, null, [], '', [], null, static fn (\Dom\Element $node) => 'app-native' === $node->localName ? $provider : null);
        $session = new HtmlRenderer()->createSession('<div style="height:16px"><app-native style="display:block;width:10px"></app-native></div>', $context);
        self::assertSame(120.0, $session->render()->getBounds($session->getDocument()->querySelector('app-native'))[0]->height);
    }
}
