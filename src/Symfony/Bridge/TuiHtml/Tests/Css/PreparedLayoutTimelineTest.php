<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Bridge\TuiHtml\Widget\InputWidget;

class PreparedLayoutTimelineTest extends TestCase
{
    public function testGeometryTrialsKeepThePreFrameTimelineAndCapturedInputs()
    {
        $now = 0.0;
        $started = 0;
        $root = new ContainerWidget()->setId('port');
        $root->add($text = new InputWidget()->setValue('Target')->setId('animated'));
        $text->setFocused(true);
        $sheet = CssStyleSheet::fromString('@keyframes detect{from,to{--show:block}}@keyframes fade{from{opacity:0}to{opacity:1}}#port{--show:none;overflow:auto;animation:detect linear;animation-timeline:scroll(self)}#port::scroll-button(down){content:"v"}p{display:var(--show);animation:fade 1s linear both}p:focus{font-weight:700}p::before{content:"Prefix";color:currentColor}');
        $sheet->describe($text, 'p');
        $sheet->setAnimationHandlers(static function () use (&$started): void { ++$started; }, static function () use (&$now): float { return $now; });
        $enrich = static function (array $snapshot): void {};
        $colors = static function (array $snapshot) use ($text): \SplObjectStorage {
            $values = new \SplObjectStorage();
            $values[$snapshot['nodes'][$text]] = '#123456';

            return $values;
        };
        foreach ([[0.0, '0'], [.5, '0.5']] as [$time, $expected]) {
            $now = $time;
            $snapshot = $sheet->prepareProjection($root, new MediaContext(), $enrich, null, $colors);
            $node = $snapshot['nodes'][$text];
            self::assertSame('none', $snapshot['styles'][$node]['display']);
            $trial = $snapshot['layout-trial'];
            $metrics = new \SplObjectStorage();
            $metrics[$snapshot['nodes'][$root]] = ['scroll' => ['client-height' => 32.0, 'content-height' => 64.0, 'offset-y' => 0.0, 'scrollable' => ['bottom']]];
            $first = $trial->resolve($metrics);
            $second = $trial->resolve($metrics);
            self::assertSame($expected, $first['styles'][$node]['opacity']);
            self::assertSame($first['styles'][$node], $second['styles'][$node]);
            self::assertSame('700', $second['styles'][$node]['font-weight']);
            self::assertSame('#123456', $second['resolver']->getPseudoStyle($node, 'before')['color']);
            $trial->commit($second);
            self::assertTrue($sheet->isAnimating());
        }
        self::assertSame(1, $started);
        $sheet->releaseProjection($root);
        $this->expectException(\LogicException::class);
        $trial->resolve(new \SplObjectStorage());
    }
}
