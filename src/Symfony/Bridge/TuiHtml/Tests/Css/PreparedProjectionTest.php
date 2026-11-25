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
use Symfony\Bridge\TuiHtml\Widget\TextWidget;

final class PreparedProjectionTest extends TestCase
{
    public function testEnrichmentPrecedesTheSingleCascadeAndWarmProjectionReusesItsLease()
    {
        $root = new ContainerWidget();
        $root->add($text = new TextWidget('text'));
        $sheet = CssStyleSheet::fromString('p:empty{color:red}p{font-weight:700}p::before{content:"before";color:blue}');
        $sheet->describe($text, 'p');
        $enrichments = 0;
        $enrich = static function (array $snapshot) use ($text, &$enrichments): void {
            ++$enrichments;
            $node = $snapshot['nodes'][$text];
            $node->replaceChildren();
            if ('' !== $text->getText()) {
                $node->textContent = $text->getText();
            }
        };
        $first = $sheet->prepareProjection($root, new MediaContext(), $enrich);
        $node = $first['nodes'][$text];
        self::assertSame('text', $node->textContent);
        self::assertNotSame('red', $first['styles'][$node]['color']);
        self::assertSame('700', $first['styles'][$node]['font-weight']);
        self::assertSame('"before"', $first['resolver']->getPseudoStyle($node, 'before')['content']);
        $warm = $sheet->prepareProjection($root, new MediaContext(), $enrich);
        self::assertSame(1, $enrichments);
        self::assertSame($first['styles'], $warm['styles']);
        self::assertSame($first['resolver'], $warm['resolver']);
        $text->setText('');
        $changed = $sheet->prepareProjection($root, new MediaContext(), $enrich);
        self::assertSame(2, $enrichments);
        self::assertSame($node, $changed['nodes'][$text]);
        self::assertSame('red', $changed['styles'][$node]['color']);
        self::assertNotSame($first['styles'], $changed['styles']);
        self::assertSame('"before"', $first['resolver']->getPseudoStyle($node, 'before')['content']);
    }

    public function testReleaseDropsTheEnrichmentOwnerAndPermitsRemounting()
    {
        $root = new ContainerWidget();
        $root->add($text = new TextWidget('text'));
        $sheet = CssStyleSheet::fromString('p{color:red}');
        $sheet->describe($text, 'p');
        $owner = new \stdClass();
        $weak = \WeakReference::create($owner);
        $first = $sheet->prepareProjection($root, new MediaContext(), static function (array $snapshot) use ($owner): void {
            $owner->document = $snapshot['document'];
        });
        unset($owner);
        self::assertNotNull($weak->get());
        $sheet->releaseProjection(new ContainerWidget());
        self::assertNotNull($weak->get());
        $sheet->releaseProjection($root);
        gc_collect_cycles();
        self::assertNull($weak->get());
        $second = $sheet->prepareProjection($root, new MediaContext(), static function (array $snapshot): void {});
        self::assertSame($first['nodes'][$text], $second['nodes'][$text]);
        self::assertSame('red', $second['styles'][$second['nodes'][$text]]['color']);
    }

    public function testRootDefaultColorRemainsBelowAuthoredRulesAndChangesTheWarmPolicyKey()
    {
        $root = new ContainerWidget();
        $root->add($plain = new TextWidget('plain'));
        $root->add($authored = new TextWidget('authored')->setId('authored'));
        $sheet = CssStyleSheet::fromString('#authored{color:red}');
        $enrich = static function (array $snapshot): void {};
        $first = $sheet->prepareProjection($root, new MediaContext(), $enrich, '#123456');
        self::assertSame('#123456', $first['styles'][$first['nodes'][$plain]]['color']);
        self::assertSame('red', $first['styles'][$first['nodes'][$authored]]['color']);
        $second = $sheet->prepareProjection($root, new MediaContext(), $enrich, '#654321');
        self::assertSame('#654321', $second['styles'][$second['nodes'][$plain]]['color']);
        self::assertSame('red', $second['styles'][$second['nodes'][$authored]]['color']);
        self::assertNotSame($first['styles'], $second['styles']);
    }

    public function testNativeColorFallbacksStayBelowAuthoredColorsAndRefreshWithWidgetState()
    {
        $root = new ContainerWidget();
        $root->add($plain = new TextWidget('plain'));
        $root->add($authored = new TextWidget('authored')->setId('authored'));
        $sheet = CssStyleSheet::fromString('#authored{color:red!important}');
        $enrich = static function (array $snapshot): void {};
        $color = '#112233';
        $nativeColors = static function (array $snapshot) use ($plain, $authored, &$color): \SplObjectStorage {
            $colors = new \SplObjectStorage();
            $colors[$snapshot['nodes'][$plain]] = $color;
            $colors[$snapshot['nodes'][$authored]] = $color;

            return $colors;
        };
        $first = $sheet->prepareProjection($root, new MediaContext(), $enrich, '#123456', $nativeColors);
        self::assertSame('#112233', $first['styles'][$first['nodes'][$plain]]['color']);
        self::assertSame('red', $first['styles'][$first['nodes'][$authored]]['color']);
        $warm = $sheet->prepareProjection($root, new MediaContext(), $enrich, '#123456', $nativeColors);
        self::assertSame($first['styles'], $warm['styles']);
        $color = '#334455';
        $plain->setText('changed');
        $next = $sheet->prepareProjection($root, new MediaContext(), $enrich, '#123456', $nativeColors);
        self::assertSame('#334455', $next['styles'][$next['nodes'][$plain]]['color']);
        self::assertSame('red', $next['styles'][$next['nodes'][$authored]]['color']);
    }

    public function testNodeUserAgentDeclarationsIncludePseudoRolesBelowAuthorRules()
    {
        $root = new ContainerWidget();
        $root->add($plain = new TextWidget('plain'));
        $root->add($authored = new TextWidget('authored')->setId('authored'));
        $sheet = CssStyleSheet::fromString('#authored{box-sizing:content-box} #authored::before{box-sizing:content-box}');
        $capture = $sheet->prepareProjection($root, new MediaContext(), static function (array $snapshot): void {}, null, static function (array $snapshot) use ($plain, $authored): \SplObjectStorage {
            $styles = new \SplObjectStorage();
            foreach ([$plain, $authored] as $widget) {
                $styles[$snapshot['nodes'][$widget]] = ['' => ['box-sizing' => 'border-box'], 'before' => ['box-sizing' => 'border-box']];
            }

            return $styles;
        });
        self::assertSame('border-box', $capture['styles'][$capture['nodes'][$plain]]['box-sizing']);
        self::assertSame('content-box', $capture['styles'][$capture['nodes'][$authored]]['box-sizing']);
        self::assertSame('border-box', $capture['resolver']->getPseudoStyle($capture['nodes'][$plain], 'before')['box-sizing']);
        self::assertSame('content-box', $capture['resolver']->getPseudoStyle($capture['nodes'][$authored], 'before')['box-sizing']);
    }
}
