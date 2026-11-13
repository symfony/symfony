<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Css;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Compatibility\Support;
use Symfony\Component\Tui\Compatibility\SupportLevel;
use Symfony\Component\Tui\Css\DeclarationMapper;
use Symfony\Component\Tui\Css\Value;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\AutoMargin;
use Symfony\Component\Tui\Style\BackgroundLayout;
use Symfony\Component\Tui\Style\BackgroundLength;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\CursorShape;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Filter;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Style\Outline;
use Symfony\Component\Tui\Style\PositionMode;
use Symfony\Component\Tui\Style\RasterImage;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TableRole;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Style\UnderlineStyle;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tests\Fixtures\CollectingLogger;

final class DeclarationMapperTest extends TestCase
{
    private CollectingLogger $logger;
    private DeclarationMapper $mapper;

    protected function setUp(): void
    {
        $this->logger = new CollectingLogger();
        $this->mapper = new DeclarationMapper($this->logger);
    }

    public function testColors()
    {
        $style = $this->map(['color' => 'red', 'background-color' => '#112233']);

        $this->assertSame(Color::named('red')->toForegroundCode(), $style->getColor()->toForegroundCode());
        $this->assertSame('#112233', $style->getBackground()->toHex());
    }

    public function testBackgroundShorthandUsesTheColor()
    {
        $this->assertSame('#112233', $this->map(['background' => 'url(x.png) #112233 no-repeat'])->getBackground()->toHex());
        $this->assertNull($this->map(['background' => 'transparent'])->getBackground());
    }

    public function testBackgroundShorthandGivesBackWhatItDoesNotSetToItsInitialValue()
    {
        foreach (['0 0', 'none', 'no-repeat center'] as $value) {
            $style = $this->map(['background-color' => 'red', 'background-image' => 'linear-gradient(red, blue)', 'background' => $value]);
            $this->assertNull($style->getBackground(), $value);
            $this->assertNull($style->getGradient(), $value);
        }
        $style = $this->map(['background-image' => 'linear-gradient(red, blue)', 'background' => '#0000ff']);
        $this->assertSame('#0000ff', $style->getBackground()->toHex());
        $this->assertNull($style->getGradient(), 'Its color, no image.');
    }

    public function testCurrentColor()
    {
        $style = $this->map(['color' => 'cyan', 'border' => '1px solid currentColor', 'background-color' => 'currentcolor']);

        $this->assertSame(Color::named('cyan')->toForegroundCode(), $style->getBorder()->color->toForegroundCode());
        $this->assertSame(Color::named('cyan')->toForegroundCode(), $style->getBackground()->toForegroundCode());

        $style = $this->map(['border-color' => 'currentcolor', 'color' => 'currentcolor']);
        $this->assertNull($style->getBorder(), 'Without a color, currentcolor declarations are ignored.');
        $this->assertNull($style->getColor());
        $this->assertSame([], $this->logger->messages);
    }

    public function testLogicalBorderSides()
    {
        $block = $this->map(['border-block' => '1px double red'])->getBorder();
        $this->assertSame([1, 0, 1, 0], [$block->top, $block->right, $block->bottom, $block->left]);

        $inline = $this->map(['border-inline' => '1px solid', 'border-inline-end' => 'none'])->getBorder();
        $this->assertSame([0, 0, 0, 1], [$inline->top, $inline->right, $inline->bottom, $inline->left]);

        $start = $this->map(['border-block-start' => 'thin solid', 'border-block-end' => '1px solid', 'border-inline-start' => '1px solid'])->getBorder();
        $this->assertSame([1, 0, 1, 1], [$start->top, $start->right, $start->bottom, $start->left]);
    }

    public function testFontShorthand()
    {
        $style = $this->map(['font' => 'italic bold 12px/30px Georgia, serif']);
        $this->assertTrue($style->getBold());
        $this->assertTrue($style->getItalic());

        $style = $this->map(['font-weight' => 'bold', 'font' => '16px monospace']);
        $this->assertFalse($style->getBold(), 'The shorthand resets the weight.');
        $this->assertFalse($style->getItalic());

        $this->assertTrue($this->map(['font' => '700 1rem sans-serif'])->getBold());
    }

    public function testFlexFlow()
    {
        $this->assertSame(Direction::Vertical, $this->map(['display' => 'flex', 'flex-flow' => 'column wrap'])->getDirection());
        $this->assertSame(Direction::Horizontal, $this->map(['flex-flow' => 'wrap row'])->getDirection());
        $this->assertNull($this->map(['flex-flow' => 'wrap'])->getDirection());
        $this->assertNull($this->map(['flex-flow' => 'diagonal'])->getDirection());
        $this->assertSame(['Invalid value "diagonal" for the "flex-flow" CSS property.'], $this->logger->messages);
    }

    public function testPlaceShorthands()
    {
        $row = $this->map(['display' => 'flex', 'place-items' => 'end', 'place-content' => 'stretch center']);
        $this->assertSame(VerticalAlign::Bottom, $row->getVerticalAlign());
        $this->assertSame(Align::Center, $row->getAlign());

        $column = $this->map(['display' => 'flex', 'flex-direction' => 'column', 'place-content' => 'end', 'place-items' => 'center']);
        $this->assertSame(VerticalAlign::Bottom, $column->getVerticalAlign());
        $this->assertSame(Align::Center, $column->getAlign());

        $longhand = $this->map(['display' => 'flex', 'justify-content' => 'start', 'place-content' => 'end']);
        $this->assertSame(Align::Left, $longhand->getAlign(), 'The longhand wins.');
    }

    public function testMathInProperties()
    {
        $style = $this->map(['padding' => 'calc(16px * 2) max(1ch, 3)', 'max-width' => 'min(40ch, 100)']);

        $this->assertSame([2, 3, 2, 3], [$style->getPadding()->top, $style->getPadding()->right, $style->getPadding()->bottom, $style->getPadding()->left]);
        $this->assertSame(40, $style->getMaxColumns());
    }

    public function testSupports()
    {
        $this->assertTrue($this->mapper->supports('color', 'rebeccapurple'));
        $this->assertTrue($this->mapper->supports('COLOR', ' red '));
        $this->assertFalse($this->mapper->supports('color', 'nope'));
        $this->assertTrue($this->mapper->supports('color', 'inherit'));
        $this->assertTrue($this->mapper->supports('--anything', 'x'));
        $this->assertFalse($this->mapper->supports('zoom', '2'), 'Ignored properties have no effect.');
        $this->assertFalse($this->mapper->supports('nope', '0'));
        $this->assertTrue($this->mapper->supports('border', '1px solid currentcolor'));
        $this->assertTrue($this->mapper->supports('row-gap', 'calc(1 + 1)'));
        $this->assertFalse($this->mapper->supports('gap', '5%'));
        $this->assertTrue($this->mapper->supports('place-items', 'center stretch'));
        $this->assertFalse($this->mapper->supports('justify-content', 'diagonal'));
        $this->assertSame([], $this->logger->messages, 'Probing does not report.');
    }

    public function testFlexWrapAndAlignSelf()
    {
        $media = new MediaContext();
        $style = $this->mapper->map(['display' => 'flex', 'flex-wrap' => 'wrap', 'gap' => '2 3ch'], $media);
        $this->assertTrue($style->getWrap());
        $this->assertSame(2, $style->getRowGap());
        $this->assertSame(3, $style->getGap());

        $this->assertTrue($this->mapper->map(['display' => 'flex', 'flex-flow' => 'row wrap'], $media)->getWrap());
        $this->assertFalse($this->mapper->map(['display' => 'flex', 'flex-flow' => 'row nowrap'], $media)->getWrap());
        $this->assertNull($this->mapper->map(['display' => 'flex', 'flex-wrap' => 'wrap', 'flex-direction' => 'column'], $media)->getWrap(), 'Columns do not wrap.');

        $this->assertTrue($this->mapper->map(['flex-direction' => 'row-reverse'], $media)->getReverseOrder());
        $this->assertTrue($this->mapper->map(['flex-flow' => 'column-reverse wrap'], $media)->getReverseOrder());
        $this->assertFalse($this->mapper->map(['flex-direction' => 'row'], $media)->getReverseOrder());

        $this->assertSame(VerticalAlign::Bottom, $this->mapper->map(['align-self' => 'flex-end'], $media)->getAlignSelf());
        $this->assertSame(VerticalAlign::Stretch, $this->mapper->map(['align-self' => 'stretch'], $media)->getAlignSelf());
        $this->assertNull($this->mapper->map(['align-self' => 'auto'], $media)->getAlignSelf());
        $this->assertFalse($this->mapper->supports('align-self', 'sideways'));
    }

    public function testPercentageHeights()
    {
        $style = $this->mapper->map(['height' => '50%'], new MediaContext());
        $this->assertSame([50, 50], [$style->getMinRowsPercent(), $style->getMaxRowsPercent()]);
        $this->assertNull($style->getMinRows());

        $style = $this->mapper->map(['min-height' => '25%', 'max-height' => '8'], new MediaContext());
        $this->assertSame([25, null], [$style->getMinRowsPercent(), $style->getMaxRowsPercent()]);
        $this->assertSame(8, $style->getMaxRows());

        $this->assertNull($this->mapper->map(['height' => '150%'], new MediaContext())->getMaxRowsPercent());
        $this->assertNull($this->mapper->map(['height' => '50%', 'max-height' => 'none'], new MediaContext())->getMaxRowsPercent());
    }

    public function testDecorationStyleAndColor()
    {
        $media = new MediaContext();
        $style = $this->mapper->map(['text-decoration' => 'underline wavy red'], $media);
        $this->assertTrue($style->getUnderline());
        $this->assertSame(UnderlineStyle::Curly, $style->getUnderlineStyle());
        $this->assertEquals(Color::from('red'), $style->getUnderlineColor());

        $style = $this->mapper->map(['text-decoration-line' => 'underline', 'text-decoration-style' => 'dotted', 'text-decoration-color' => '#ff8800'], $media);
        $this->assertSame(UnderlineStyle::Dotted, $style->getUnderlineStyle());
        $this->assertEquals(Color::from('#ff8800'), $style->getUnderlineColor());
        $this->assertStringContainsString("\x1b[4:4m", $style->apply('x'));
        $this->assertStringContainsString("\x1b[58;2;255;136;0m", $style->apply('x'));

        $this->assertNull($this->mapper->map(['text-decoration' => 'underline sparkly'], $media)->getUnderline(), 'An unknown part invalidates the shorthand.');
        $this->assertFalse($this->mapper->supports('text-decoration-style', 'zigzag'));
    }

    public function testOutlineRepaintsTheBorder()
    {
        $media = new MediaContext();
        $this->assertEquals(Color::from('cyan'), $this->mapper->map(['border' => '1px solid red', 'outline' => '2px solid cyan'], $media)->getBorder()?->color);
        $this->assertEquals(Color::from('green'), $this->mapper->map(['border' => '1px solid', 'outline-style' => 'dashed', 'outline-color' => 'green'], $media)->getBorder()?->color);
        $this->assertEquals(Color::from('red'), $this->mapper->map(['border' => '1px solid red', 'outline' => 'none'], $media)->getBorder()?->color);
        $this->assertNull($this->mapper->map(['outline' => '2px solid cyan'], $media)->getBorder(), 'Without a border, nothing is drawn.');
    }

    public function testOrderAndMinWidth()
    {
        $media = new MediaContext();
        $this->assertSame(-2, $this->mapper->map(['order' => '-2'], $media)->getOrder());
        $this->assertNull($this->mapper->map(['order' => '1.5'], $media)->getOrder());
        $this->assertSame(12, $this->mapper->map(['min-width' => '12ch'], $media)->getMinColumns());
        $this->assertNull($this->mapper->map(['min-width' => 'auto'], $media)->getMinColumns());
        $this->assertTrue($this->mapper->supports('min-width', '96px'));
        $this->assertTrue($this->mapper->supports('min-width', '50%'), 'The least width of a box sized by its content.');
        $this->assertFalse($this->mapper->supports('min-width', 'wide'));
    }

    public function testAutoMargins()
    {
        $media = new MediaContext();
        $this->assertSame(AutoMargin::Both, $this->mapper->map(['margin' => '1 auto'], $media)->getAutoMargin());
        $this->assertSame(AutoMargin::Left, $this->mapper->map(['margin-left' => 'AUTO'], $media)->getAutoMargin());
        $this->assertSame(AutoMargin::Both, $this->mapper->map(['margin-inline' => 'auto'], $media)->getAutoMargin());
        $this->assertNull($this->mapper->map(['margin' => 'auto 0'], $media)->getAutoMargin(), 'Vertical auto margins are 0.');
        $this->assertSame(AutoMargin::Right, $this->mapper->map(['margin' => '0 auto', 'margin-left' => '2'], $media)->getAutoMargin(), 'A later side resets it.');
        $this->assertSame(2, $this->mapper->map(['margin' => '0 auto', 'margin-left' => '2'], $media)->getMargin()?->left);
    }

    public function testLogicalPositionArea()
    {
        $area = fn (string $value) => $this->mapper->map(['position' => 'fixed', 'position-anchor' => '--a', 'position-area' => $value], new MediaContext())->getPosition()?->area;

        $this->assertSame(['top', 'span-left'], $area('span-x-start top'));
        $this->assertSame(['bottom', 'center'], $area('block-end'));
        $this->assertSame(['top', 'right'], $area('start end'), 'The block axis first, then the inline one.');
        $this->assertSame(['bottom', 'right'], $area('end'), 'Alone: both axes.');
        $this->assertSame(['span-top', 'span-right'], $area('span-inline-end span-block-start'));
        $this->assertFalse($this->mapper->supports('position-area', 'x-start inline-end'), 'Two keywords of the same axis.');
    }

    #[RequiresPhpExtension('gd')]
    public function testBackgroundImage()
    {
        $image = new RasterImage((string) file_get_contents(__DIR__.'/../Fixtures/Compat/quadrants.png'));
        $loaded = [];
        $this->mapper->setImageLoader(static function (string $url) use ($image, &$loaded): ?RasterImage {
            $loaded[] = $url;

            return 'quadrants.png' === $url ? $image : null;
        });
        $media = new MediaContext();

        $this->assertSame($image, $this->mapper->map(['background-image' => 'url("quadrants.png"), linear-gradient(red, blue)'], $media)->getBackgroundImage());
        $this->assertNull($this->mapper->map(['background-image' => 'url(quadrants.png)'], $media)->getGradient());

        // In a color space: stops between the stops, in that space
        $gradient = $this->mapper->map(['background-image' => 'linear-gradient(to right in hsl longer hue, #ff0000, #0000ff)'], $media)->getGradient();
        $this->assertSame([90.0, 9], [$gradient?->angle, \count($gradient?->stops ?? [])]);
        $this->assertSame('#00ff00', $gradient?->stops[4][0]->toHex(), 'Halfway around the hues the long way: green.');
        $this->assertSame(0.5, $gradient?->stops[4][1]);
        $this->assertCount(2, $this->mapper->map(['background-image' => 'linear-gradient(in srgb, #ff0000, #0000ff)'], $media)->getGradient()?->stops ?? []);
        $this->assertNull($this->mapper->map(['background-image' => 'linear-gradient(in oklab longer hue, red, blue)'], $media)->getGradient(), 'A hue method is for the polar spaces.');
        $this->assertSame($image, $this->mapper->map(['background' => 'navy url(quadrants.png) center / contain no-repeat'], $media)->getBackgroundImage());
        $this->assertSame('contain', $this->mapper->map(['background' => 'navy url(quadrants.png) center / contain no-repeat'], $media)->getBackgroundLayout()?->fit);
        $this->assertNull($this->mapper->map(['background-image' => 'url(nowhere.png)'], $media)->getBackgroundImage(), 'An image that cannot be loaded is none.');
        $this->assertNull($this->mapper->map(['background-image' => 'url(quadrants.png)'], new MediaContext(forcedColors: true))->getBackgroundImage(), 'Forced colors: none.');
        $this->assertNull(Style::mergeAll([(new Style())->withBackgroundImage($image), $this->mapper->map(['background-image' => 'none'], $media)])->getBackgroundImage(), 'none removes an image set before.');
        $this->assertContains('nowhere.png', $loaded);
    }

    public function testBackgroundLayout()
    {
        $media = new MediaContext();
        $this->assertNull($this->mapper->map(['background' => 'red'], $media)->getBackgroundLayout(), 'Without a layout value, the defaults.');

        $layout = $this->mapper->map(['background' => 'linear-gradient(red, blue) right 2ch bottom 1 / 50% auto no-repeat content-box padding-box'], $media)->getBackgroundLayout();
        $this->assertSame([BackgroundLayout::NO_REPEAT, BackgroundLayout::NO_REPEAT], $layout->repeat);
        $this->assertSame([BackgroundLayout::CONTENT_BOX, BackgroundLayout::PADDING_BOX], [$layout->origin, $layout->clip]);
        $this->assertEquals([new BackgroundLength(50.0), null], $layout->size);
        $this->assertEquals([new BackgroundLength(0.0, 2, true), new BackgroundLength(0.0, 1, true)], $layout->position);
        $this->assertSame(46, $layout->position[0]->resolve(60, 12), 'From the right edge.');

        $layout = $this->mapper->map(['background-position' => 'top', 'background-repeat' => 'repeat-y', 'background-clip' => 'text'], $media)->getBackgroundLayout();
        $this->assertEquals([new BackgroundLength(50.0), new BackgroundLength(0.0)], $layout->position, 'One vertical keyword: the other axis is centered.');
        $this->assertSame([BackgroundLayout::NO_REPEAT, BackgroundLayout::REPEAT], $layout->repeat);
        $this->assertSame([BackgroundLayout::TEXT, BackgroundLayout::PADDING_BOX], [$layout->clip, $layout->origin]);

        $layout = $this->mapper->map(['background-size' => '4ch', 'background' => 'blue', 'background-position-x' => '25%'], $media)->getBackgroundLayout();
        $this->assertSame([null, null], $layout->size, 'The shorthand after a longhand resets it.');
        $this->assertEquals(new BackgroundLength(25.0), $layout->position[0]);

        foreach (['background-position' => ['top 2ch', 'left right', 'center 1 top', '1 2 3 4 5'], 'background-size' => ['-1ch', '1 2 3'], 'background-repeat' => ['repeat-x repeat'], 'background-origin' => ['text']] as $property => $values) {
            foreach ($values as $value) {
                $this->assertFalse($this->mapper->supports($property, $value), $property.': '.$value);
            }
        }
        $this->assertTrue($this->mapper->supports('background-position', 'center top'));
        $this->assertTrue($this->mapper->supports('background-position', 'bottom 10% right'));
        $this->assertSame(Color::palette(1)->toBackgroundCode(), $this->mapper->map(['background' => '1'], $media)->getBackground()?->toBackgroundCode(), 'A number alone stays a palette color.');
        $this->assertSame(Color::named('green')->toBackgroundCode(), $this->mapper->map(['background' => 'green linear-gradient(red, blue) 0 0 / 4ch 1'], $media)->getBackground()?->toBackgroundCode(), 'Numbers among other values are positions and sizes.');
    }

    public function testVendorPrefixesStandForTheProperties()
    {
        $style = $this->map(['-o-object-fit' => 'cover', '-moz-user-select' => 'none', '-webkit-mask-image' => 'none', '-ms-text-align' => 'center']);

        $this->assertSame([], $this->logger->messages, 'A prefixed property is the one it prefixes, supported or ignored.');
        $this->assertSame(TextAlign::Center, $style->getTextAlign());
        $this->assertTrue($this->mapper->supports('-webkit-text-align', 'center'));
        $this->map(['-webkit-nonsense' => '1']);
        $this->assertSame(['The "-webkit-nonsense" CSS property is not supported.'], $this->logger->messages);
    }

    public function testIgnoredPropertiesAreNotReported()
    {
        (new DeclarationMapper(strict: true))->map(['transform-origin' => 'center', 'font-variant-ligatures' => 'none', 'fill' => 'red', 'mask-image' => 'none', 'text-emphasis' => 'dot'], new MediaContext());
        $this->map(['view-transition-name' => 'card']);

        $this->assertSame([], $this->logger->messages);
        $this->assertSame(SupportLevel::Ignored, $this->mapper->getSupport()['css.properties.transform-origin']->level);
    }

    public function testRegisteredPropertyIsNotIgnoredAnymore()
    {
        $this->assertFalse($this->mapper->supports('white-space', 'nowrap'));
        $this->assertSame(SupportLevel::Ignored, $this->mapper->getSupport()['css.properties.white-space']->level);

        $this->mapper->addProperty('white-space', static fn (Style $style, string $value) => 'nowrap' === $value ? $style : false, Support::partial('nowrap only.'));

        $this->assertTrue($this->mapper->supports('white-space', 'nowrap'));
        $this->assertFalse($this->mapper->supports('white-space', 'sideways'));
        $this->assertSame(SupportLevel::Partial, $this->mapper->getSupport()['css.properties.white-space']->level);

        $this->mapper->map(['white-space' => 'sideways'], new MediaContext());
        $this->assertCount(1, $this->logger->messages, 'An invalid value of a registered property is reported.');
    }

    public function testInvalidColorIsReported()
    {
        $style = $this->map(['color' => 'nope']);

        $this->assertNull($style->getColor());
        $this->assertSame(['Invalid value "nope" for the "color" CSS property.'], $this->logger->messages);
    }

    public function testForcedColorsDropsColors()
    {
        $border = $this->mapper->map(['border' => '1px solid red', 'border-left' => '1px solid blue'], new MediaContext(forcedColors: true))->getBorder();
        $this->assertNull($border->color, 'Border colors are dropped too.');
        $this->assertSame(1, $border->left, 'The border itself stays: it is not a color cue.');

        $style = $this->map(['color' => 'red', 'background-color' => 'blue', 'font-weight' => 'bold'], new MediaContext(forcedColors: true));
        $this->assertNull($this->map(['background' => 'blue'], new MediaContext(forcedColors: true))->getBackground());

        $this->assertNull($style->getColor());
        $this->assertNull($style->getBackground());
        $this->assertTrue($style->getBold());
    }

    public function testPadding()
    {
        $this->assertSame([1, 1, 1, 1], self::padding($this->map(['padding' => '1'])));
        $this->assertSame([1, 2, 1, 2], self::padding($this->map(['padding' => '1 2'])));
        $this->assertSame([1, 2, 3, 2], self::padding($this->map(['padding' => '1 2 3'])));
        $this->assertSame([1, 2, 3, 4], self::padding($this->map(['padding' => '1 2 3 4'])));
        $this->assertSame([1, 2, 1, 2], self::padding($this->map(['padding' => '16px 16px'])), 'Vertical px use the cell height, horizontal ones the cell width.');
        $this->assertSame([0, 0, 0, 0], self::padding($this->map(['padding' => '-1'])));
        $this->assertSame([0, 2, 0, 2], self::padding($this->map(['padding' => '.5rem 1rem'])), 'Half a row is none: a row would double the height of a line.');
        $this->assertSame([1, 2, 1, 2], self::padding($this->map(['padding' => '.75rem'])), 'A row from three quarters of one; 1.5 columns are 2.');
        $this->assertSame([2, 0, 1, 0], self::padding($this->map(['padding' => '2rem 0 1.5rem'])));
        $this->assertNull($this->map(['padding' => '1 2 3 4 5'])->getPadding());
        $this->assertNull($this->map(['padding' => 'calc(100% - min(10%, 2ch))'])->getPadding(), 'Neither linear in the width nor the smallest or largest of such sizes.');
    }

    public function testPercentagePaddingAndMargins()
    {
        $padding = $this->map(['padding' => '1 10%'])->getPadding();
        $this->assertSame([1 => [10.0, 0.0], 3 => [10.0, 0.0]], $padding?->percents);
        $this->assertSame([1, 4, 1, 4], self::padding(new Style(padding: $padding->resolve(40))), 'Shares of the width of the containing block.');

        // Not linear in the width: the smallest or largest of such sizes, like clamp()
        $padding = $this->map(['padding' => 'max(1ch, 10%) clamp(2ch, 20%, 6ch) calc(min(30%, 3ch)) min(max(5%, 2ch), 50%)'])->getPadding();
        $this->assertNotNull($padding);
        $this->assertSame([1, 2, 3, 2], self::padding(new Style(padding: $padding->resolve(10))));
        $this->assertSame([6, 6, 3, 3], self::padding(new Style(padding: $padding->resolve(60))));

        $margin = $this->map(['margin' => '5% auto', 'margin-left' => 'calc(10% + 1ch)'])->getMargin();
        $this->assertNotNull($margin);
        $this->assertSame([2, 0, 2, 5], self::padding(new Style(padding: $margin->resolve(40))), 'Vertical sides are shares of the width too.');
        $this->assertSame(AutoMargin::Right, $this->map(['margin' => '5% auto', 'margin-left' => '10%'])->getAutoMargin());

        $merged = $this->map(['padding' => '10%', 'padding-left' => '2'])->getPadding();
        $this->assertSame([4, 4, 4, 2], self::padding(new Style(padding: $merged?->resolve(40))), 'A length side over a percentage one.');
    }

    public function testPaddingSides()
    {
        $this->assertSame([1, 2, 3, 4], self::padding($this->map(['padding-top' => '1', 'padding-right' => '2', 'padding-bottom' => '3', 'padding-left' => '4'])));
        $this->assertSame([0, 2, 0, 2], self::padding($this->map(['padding-inline' => '2'])));
        $this->assertSame([0, 3, 0, 2], self::padding($this->map(['padding-inline' => '2 3'])), 'Start then end: left then right.');
        $this->assertSame([1, 0, 1, 0], self::padding($this->map(['padding-block' => '1'])));
        $this->assertSame([5, 1, 5, 1], self::padding($this->map(['padding' => '5 0', 'padding-inline' => '1'])));
        $this->assertNull($this->map(['padding-top' => 'auto'])->getPadding());
    }

    public function testMargin()
    {
        $margin = static fn (Style $s) => [$s->getMargin()->top, $s->getMargin()->right, $s->getMargin()->bottom, $s->getMargin()->left];

        $this->assertSame([1, 2, 1, 2], $margin($this->map(['margin' => '1 2ch'])));
        $this->assertSame([0, 0, 0, 3], $margin($this->map(['margin-left' => '3'])));
        $this->assertSame([0, 0, 0, 0], $margin($this->map(['margin' => 'auto'])), 'auto centers nothing: it is 0.');
        $this->assertSame([0, 0, 0, 0], $margin($this->map(['margin' => '-2'])), 'Negative margins are 0.');
        $this->assertSame([1, 0, 1, 0], $margin($this->map(['margin-block' => '16px'])));
    }

    public function testPercentageWidths()
    {
        $this->assertSame([100.0, null], [$this->map(['min-width' => '100%'])->getMinWidthPercent(), $this->map(['min-width' => '100%'])->getMinColumns()], 'The least width of a box sized by its content.');
        $this->assertNull($this->map(['min-width' => '100%', 'min-width' => '4ch'])->getMinWidthPercent());
        $this->assertSame(30, $this->map(['width' => '30%'])->getWidthPercent());
        $this->assertSame(50, $this->map(['max-width' => '49.6%'])->getWidthPercent());
        $this->assertSame(100, $this->map(['width' => '150%'])->getWidthPercent(), 'Clamped to the container.');
        $this->assertNull($this->map(['width' => '30%', 'max-width' => 'none'])->getWidthPercent());

        // A sum without calc() in min(), max() and clamp() is one argument
        $style = $this->map(['max-width' => 'min(60ch, 100% - 4ch)']);
        $this->assertSame([100, -4, 60], [$style->getWidthPercent(), $style->getWidthOffset(), $style->getMaxColumns()]);
        // calc() of min() alone, like the max-w-[calc(min(700px,100%))] of the docs of React
        $style = $this->map(['max-width' => 'calc(min(20ch, 100%))']);
        $this->assertSame([100, 20], [$style->getWidthPercent(), $style->getMaxColumns()]);
        $padding = $this->map(['padding-left' => 'max(1ch, 50% - 15ch)'])->getPadding();
        $this->assertSame([1, 15], [$padding?->resolve(20)->left, $padding?->resolve(60)->left]);
    }

    public function testGridShorthands()
    {
        $template = fn (array $declarations) => $this->map(['display' => 'grid'] + $declarations)->getGridTemplate();

        $grid = $template(['grid-template' => '"head head" 1 [main-start] "nav main" auto / 12ch 1fr']);
        $this->assertSame(['head' => [0, 0, 0, 1], 'nav' => [1, 0, 1, 0], 'main' => [1, 1, 1, 1]], $grid->areas);
        $this->assertSame([1, null], $grid->rows, 'The height after each string.');
        $this->assertSame(['main-start' => [2]], $grid->rowLines);
        $this->assertCount(2, $grid->tracks);

        $grid = $template(['grid-template' => '3 auto / repeat(3, 1fr)']);
        $this->assertSame([3, null], $grid->rows);
        $this->assertCount(3, $grid->tracks);

        $grid = $template(['grid-template' => '"a b"', 'grid-template-columns' => '5ch 5ch 5ch']);
        $this->assertCount(3, $grid->tracks, 'A longhand after the shorthand wins.');
        $this->assertSame([0, 1, 0, 1], $grid->areas['b']);

        $grid = $template(['grid' => 'auto-flow dense 2 / 10ch 10ch']);
        $this->assertTrue($grid->dense);
        $this->assertSame(2, $grid->autoRows);
        $this->assertCount(2, $grid->tracks);

        $this->assertSame([], $template(['grid-template' => 'none'])->tracks);
        $grid = $template(['grid' => '1 auto / auto-flow dense 10ch']);
        $this->assertTrue($grid->columnFlow, 'Column after column.');
        $this->assertTrue($grid->dense);
        $this->assertSame([1, null], $grid->rows);
        $this->assertSame(['cells', 10.0, 0], $grid->autoColumns);
        $this->assertSame([], $grid->tracks, 'No explicit columns.');

        $grid = $template(['grid-auto-flow' => 'column', 'grid-auto-columns' => 'minmax(4ch, 1fr) 2ch']);
        $this->assertTrue($grid->columnFlow);
        $this->assertSame('fr', $grid->autoColumns[0] ?? null, 'The first value.');
        $this->assertFalse($template(['grid-auto-flow' => 'row dense'])->columnFlow);

        // Of the width of the grid, inside min(), max(), clamp() and calc() too
        $grid = $template(['grid-template-columns' => 'repeat(auto-fill, minmax(min(12ch, 100%), 1fr))']);
        $this->assertSame([10], $grid->resolve(10, 1, static fn () => 0), 'One column, as wide as the grid, when it is narrower than 12ch.');
        $this->assertSame([13, 13, 12], $grid->resolve(40, 1, static fn () => 0));
        $this->assertSame([19, 10, 11], $template(['grid-template-columns' => 'calc(50% - 1ch) min(10ch, 30%) 1fr'])->resolve(40, 0, static fn () => 0));
        $this->assertSame([8, 32], $template(['grid-template-columns' => 'minmax(20%, 5ch) 1fr'])->resolve(40, 0, static fn () => 0), 'The largest of the minimum and the maximum.');

        $this->map(['grid' => '1 / auto-flow / 2']);
        $this->assertNotEmpty($this->logger->messages, 'An invalid flow is reported.');
    }

    public function testBoxSizing()
    {
        $box = ['width' => '20', 'min-width' => '10', 'height' => '5', 'padding' => '1 2', 'border' => '1px solid'];

        $style = $this->map($box);
        $this->assertSame([20, 10], [$style->getMaxColumns(), $style->getMinColumns()], 'border-box: a widget width includes its padding and border.');
        $this->assertSame([1, 1], [$style->getMinRows(), $style->getMaxRows()], 'Its rows do not: those of the content are left.');
        $this->assertSame([0, 0], [$this->map(['height' => '2', 'border' => '1px solid', 'padding' => '1'])->getMinRows(), $this->map(['height' => '2', 'border' => '1px solid', 'padding' => '1'])->getMaxRows()], 'Never fewer than none.');

        $style = $this->map(['box-sizing' => 'content-box'] + $box);
        $this->assertSame([26, 16], [$style->getMaxColumns(), $style->getMinColumns()], 'content-box: the padding and the border are added.');
        $this->assertSame([5, 5], [$style->getMinRows(), $style->getMaxRows()]);

        $style = $this->map(['box-sizing' => 'content-box', 'width' => '50%', 'border' => '1px solid']);
        $this->assertSame([50, 2], [$style->getWidthPercent(), $style->getWidthOffset()], 'A percentage too, like calc(50% + 2ch).');
        $this->assertNull($this->map(['box-sizing' => 'padding-box'])->getMaxColumns());
    }

    public function testHeights()
    {
        $style = $this->map(['height' => '2']);
        $this->assertSame([2, 2], [$style->getMinRows(), $style->getMaxRows()]);

        $style = $this->map(['min-height' => '1', 'max-height' => '32px']);
        $this->assertSame([1, 2], [$style->getMinRows(), $style->getMaxRows()], 'Each bound keeps the other one.');

        $style = $this->map(['height' => '3', 'max-height' => 'none']);
        $this->assertSame([3, 3], [$style->getMinRows(), $style->getMaxRows()], 'No maximum: the height is the height.');

        $style = $this->map(['height' => '2', 'min-height' => '1', 'max-height' => '3']);
        $this->assertSame([2, 2], [$style->getMinRows(), $style->getMaxRows()], 'Within its bounds, whatever their order.');
        $this->assertSame(24, $this->map(['width' => '24', 'max-width' => '30', 'min-width' => '10'])->getMaxColumns());
        $this->assertSame(20, $this->map(['width' => '24', 'max-width' => '20'])->getMaxColumns());
    }

    public function testObjectPosition()
    {
        $position = fn (string $value) => $this->map(['object-position' => $value])->getObjectPosition();

        $this->assertSame([0.0, 0.0], $position('left top'));
        $this->assertSame([1.0, 0.0], $position('top right'), 'A vertical keyword first swaps them.');
        $this->assertSame([0.5, 1.0], $position('bottom'), 'One vertical keyword: centered across.');
        $this->assertSame([1.0, 0.5], $position('right'));
        $this->assertSame([0.25, 0.5], $position('25%'));
        $this->assertSame([0.0, 0.75], $position('0 75%'));
        $this->assertSame([-10.0, 0.5], $position('-99999999%'), 'Bounded.');
        $this->assertNull($position('left right'));
        $this->assertNull($position('top top'));
        $this->assertSame([0.0, 0.0, 2, 1], $position('2ch 1'), 'Lengths: cells from the left and the top.');
        $this->assertSame([1.0, 1.0, -2, -1], $position('right 2ch bottom 1'), 'Offsets from an edge, inwards.');
        $this->assertSame([0.9, 1.0], $position('right 10% bottom'));
        $this->assertSame([1.0, 1.0, 2, 0], $position('right -2ch bottom'), 'A negative offset from an edge, outwards.');
        $this->assertSame([1.0, 0.0, 0, 3], $position('right 3'), 'Two values: the second one is vertical.');
        $this->assertNull($position('left 2ch right'));
        $this->assertNull($position('2ch 1 3'));
    }

    public function testBorderShorthand()
    {
        $border = $this->map(['border' => '1px solid red'])->getBorder();

        $this->assertSame([1, 1, 1, 1], [$border->top, $border->right, $border->bottom, $border->left]);
        $this->assertSame(BorderPattern::normal()->getChars(), $border->pattern->getChars());
        $this->assertSame(Color::named('red')->toForegroundCode(), $border->color->toForegroundCode());

        $this->assertSame(BorderPattern::double()->getChars(), $this->map(['border' => 'double'])->getBorder()->pattern->getChars());
        $this->assertSame(BorderPattern::rounded()->getChars(), $this->map(['border' => 'thick rounded'])->getBorder()->pattern->getChars());
        $this->assertSame(0, $this->map(['border' => 'none'])->getBorder()->top);
        $this->assertSame(0, $this->map(['border' => '0'])->getBorder()->top);
        $this->assertSame(0, $this->map(['border' => '1px hidden'])->getBorder()->top);
        $this->assertSame(1, $this->map(['border' => '5px dashed'])->getBorder()->top, 'Widths are clamped to one cell.');
        $this->assertNull($this->map(['border' => '1px wavy'])->getBorder());
    }

    public function testBorderLonghands()
    {
        $border = $this->map(['border-style' => 'double', 'border-color' => 'blue'])->getBorder();
        $this->assertSame([1, 1, 1, 1], [$border->top, $border->right, $border->bottom, $border->left], 'A style alone draws a border.');
        $this->assertSame(BorderPattern::double()->getChars(), $border->pattern->getChars());
        $this->assertSame(Color::named('blue')->toForegroundCode(), $border->color->toForegroundCode());

        $border = $this->map(['border' => '1px solid', 'border-width' => '1 0'])->getBorder();
        $this->assertSame([1, 0, 1, 0], [$border->top, $border->right, $border->bottom, $border->left]);

        $border = $this->map(['border-top' => '1px double red', 'border-left' => 'solid'])->getBorder();
        $this->assertSame([1, 0, 0, 1], [$border->top, $border->right, $border->bottom, $border->left]);
        $this->assertSame(BorderPattern::double()->getChars(), $border->getSidePattern(0)->getChars(), 'Each side has its style.');
        $this->assertSame(BorderPattern::normal()->getChars(), $border->getSidePattern(3)->getChars());

        $border = $this->map(['border' => '1px solid', 'border-style' => 'solid dashed double none'])->getBorder();
        $this->assertSame([1, 1, 1, 0], [$border->top, $border->right, $border->bottom, $border->left]);
        $this->assertSame([BorderPattern::normal()->getChars(), BorderPattern::dashed()->getChars(), BorderPattern::double()->getChars()], array_map(static fn (int $side) => $border->getSidePattern($side)->getChars(), [0, 1, 2]));

        $border = $this->map(['border' => '1px solid', 'border-top-width' => 'thick'])->getBorder();
        $this->assertSame([BorderPattern::heavy()->getChars(), BorderPattern::normal()->getChars()], [$border->getSidePattern(0)->getChars(), $border->getSidePattern(1)->getChars()], 'A thick side is heavy.');

        $border = $this->map(['border' => '1px solid', 'border-bottom' => 'none'])->getBorder();
        $this->assertSame([1, 1, 0, 1], [$border->top, $border->right, $border->bottom, $border->left]);

        $this->assertSame(0, $this->map(['border' => '1px solid', 'border-style' => 'none'])->getBorder()->top);
        $this->assertNull($this->map(['border-style' => 'wavy'])->getBorder());
        $this->assertNull($this->map(['border-width' => 'auto'])->getBorder());
        $this->assertNull($this->map(['border-top' => 'nope'])->getBorder());
        $this->assertNull($this->map(['border-color' => 'nope'])->getBorder());
    }

    public function testNegativeMarginsPullTheBox()
    {
        $style = $this->map(['margin' => '-2 1 -1 -3']);
        $this->assertSame([0, 1, 0, 0], [$style->getMargin()->top, $style->getMargin()->right, $style->getMargin()->bottom, $style->getMargin()->left], 'The margins themselves are not negative.');
        $this->assertSame([2, 0, 1, 3], [$style->getMarginPull()->top, $style->getMarginPull()->right, $style->getMarginPull()->bottom, $style->getMarginPull()->left]);

        $this->assertNull($this->map(['margin-top' => '-2', 'margin' => '1'])->getMarginPull(), 'A later margin replaces the pull.');
        $this->assertNull($this->map(['padding-top' => '-2'])->getMarginPull());
    }

    public function testOutlines()
    {
        $outline = $this->map(['outline' => '1px dashed red', 'outline-offset' => '2'])->getOutline();
        $this->assertInstanceOf(Outline::class, $outline);
        $this->assertSame([BorderPattern::DASHED, 2], [$outline->pattern, $outline->offset]);
        $this->assertSame(Color::named('red')->toForegroundCode(), $outline->color?->toForegroundCode());

        $this->assertSame(BorderPattern::HEAVY, $this->map(['outline-style' => 'solid', 'outline-width' => 'thick'])->getOutline()?->pattern);
        $this->assertNull($this->map(['outline' => '1px none red'])->getOutline());
        $this->assertNull($this->map(['outline' => '0 solid red'])->getOutline());

        $style = $this->map(['border' => '1px solid', 'outline' => '1px solid red']);
        $this->assertNull($style->getOutline(), 'On a border, the outline repaints it.');
        $this->assertSame(Color::named('red')->toForegroundCode(), $style->getBorder()->color?->toForegroundCode());
        $this->assertInstanceOf(Outline::class, $this->map(['border' => '1px solid', 'outline' => '1px solid red', 'outline-offset' => '1'])->getOutline(), 'Offset out, it is drawn around the border.');
        $this->assertNull($this->map(['border' => '1px solid', 'outline' => '2px solid red', 'outline-offset' => '2px'])->getOutline(), 'The 2px of a focus ring are none: the border is repainted.');
        $this->assertSame(1, $this->map(['outline' => '1px solid', 'outline-offset' => '.5rem'])->getOutline()?->offset, 'A column from three quarters of one.');
        $this->assertNull($this->map(['outline' => '2px solid transparent', 'outline-offset' => '2px'])->getOutline(), 'Transparent, like an outline-none: none to see.');
        $this->assertNull($this->map(['outline-style' => 'solid', 'outline-color' => 'rgb(0 0 0 / 0)'])->getOutline());
        $this->assertSame('┌', $this->map(['border' => '1px solid', 'outline' => '2px solid transparent', 'outline-offset' => '3'])->getBorder()?->pattern->getChars()[0][0], 'Nor around a border.');
    }

    public function testAMaxWidthOfAllTheContainerBoundsNothing()
    {
        // The max-width: 100% the preflight of Tailwind gives images, with their width
        $style = $this->map(['width' => '1.5rem', 'max-width' => '100%']);
        $this->assertSame([3, null], [$style->getMaxColumns(), $style->getWidthPercent()], 'The width stays, not a share of the row.');
        $this->assertNull($this->map(['max-width' => '100%'])->getWidthPercent());
        $this->assertSame(50, $this->map(['max-width' => '50%'])->getWidthPercent(), 'Less than all of it is still a share.');
    }

    public function testAWidthOfZeroBeforeAStyleKeepsTheSideOff()
    {
        // The preflight of Tailwind: border-width: 0, then border-style: solid
        $border = $this->map(['border-width' => '0', 'border-style' => 'solid', 'border-color' => '#e5e7eb'])->getBorder();
        $this->assertSame([0, 0, 0, 0], [$border?->top ?? 0, $border?->right ?? 0, $border?->bottom ?? 0, $border?->left ?? 0]);

        $border = $this->map(['border-width' => '0', 'border-style' => 'solid', 'border-top-width' => '1px'])->getBorder();
        $this->assertSame([1, 0, 0, 0], [$border?->top, $border?->right, $border?->bottom, $border?->left], 'A side given a width after it.');

        $border = $this->map(['border-style' => 'solid'])->getBorder();
        $this->assertSame([1, 1, 1, 1], [$border?->top, $border?->right, $border?->bottom, $border?->left], 'Without a width, the style alone draws it, medium.');

        $border = $this->map(['border-width' => '0', 'border' => '1px solid'])->getBorder();
        $this->assertSame([1, 1, 1, 1], [$border?->top, $border?->right, $border?->bottom, $border?->left], 'A shorthand after it gives the width again.');
    }

    public function testATransparentBorderKeepsItsRoom()
    {
        $border = $this->map(['border' => '1px solid transparent'])->getBorder();
        $this->assertSame([1, ' '], [$border?->top, $border?->pattern->getChars()[0][0]], 'Spaces, in the background.');
        $this->assertSame(' ', $this->map(['border' => '1px solid', 'border-color' => 'rgb(0 0 0 / 0)'])->getBorder()?->pattern->getChars()[1][0]);
        $this->assertSame('┌', $this->map(['border' => '1px solid transparent', 'border-color' => 'red'])->getBorder()?->pattern->getChars()[0][0], 'A color after it.');

        $border = $this->map(['border' => '1px solid #fff0', 'display' => 'inline-block'])->getBorder();
        $this->assertSame([0, 1, 0, 1], [$border?->top, $border?->right, $border?->bottom, $border?->left], 'All around an inline block, like the counter of a button of GitHub: no rows, which the line would not hold.');
        $border = $this->map(['border-bottom' => '1px solid transparent', 'border-top' => '1px solid', 'display' => 'inline-block'])->getBorder();
        $this->assertSame([1, 1], [$border?->top, $border?->bottom], 'One side only, like an underline given a color on hover: its room.');
        $border = $this->map(['border' => '1px solid transparent', 'display' => 'inline-block', 'position' => 'absolute'])->getBorder();
        $this->assertSame([1, 1], [$border?->top, $border?->bottom], 'Out of the lines, a block: its room.');
    }

    public function testBorderRadiusRoundsSolidBordersWhateverTheOrder()
    {
        $rounded = BorderPattern::rounded()->getChars();

        $this->assertSame($rounded, $this->map(['border' => '1px solid', 'border-radius' => '4px'])->getBorder()->pattern->getChars());
        $this->assertSame($rounded, $this->map(['border-radius' => '4px', 'border' => '1px solid'])->getBorder()->pattern->getChars());
        $this->assertSame(BorderPattern::double()->getChars(), $this->map(['border' => 'double', 'border-radius' => '4px'])->getBorder()->pattern->getChars());
        $this->assertSame(BorderPattern::normal()->getChars(), $this->map(['border' => 'solid', 'border-radius' => '0'])->getBorder()->pattern->getChars());
        $this->assertNull($this->map(['border-radius' => '4px'])->getBorder(), 'No border, no corners.');
        $this->assertNull($this->map(['border-radius' => 'big'])->getBorder());
        $this->assertTrue($this->logger->has('"border-radius"'));
    }

    public function testText()
    {
        $style = $this->map(['font-weight' => '700', 'font-style' => 'oblique', 'text-decoration' => 'underline line-through']);
        $this->assertTrue($style->getBold());
        $this->assertTrue($style->getItalic());
        $this->assertTrue($style->getUnderline());
        $this->assertTrue($style->getStrikethrough());

        $style = $this->map(['font-weight' => 'normal', 'font-style' => 'normal', 'text-decoration-line' => 'none']);
        $this->assertFalse($style->getBold());
        $this->assertFalse($style->getItalic());
        $this->assertFalse($style->getUnderline());
        $this->assertFalse($style->getStrikethrough());

        $this->assertFalse($this->map(['font-weight' => '400'])->getBold());
        $this->assertTrue($this->map(['font-weight' => 'bolder'])->getBold());
        $this->assertNull($this->map(['font-weight' => 'heavy'])->getBold());
        $this->assertNull($this->map(['font-style' => 'slanted'])->getItalic());
        $this->assertNull($this->map(['text-decoration' => 'overline'])->getUnderline());
    }

    public function testOpacityAndFilter()
    {
        $this->assertTrue($this->map(['opacity' => '0.5'])->getDim());
        $this->assertTrue($this->map(['opacity' => '50%'])->getDim());
        $this->assertFalse($this->map(['opacity' => '1'])->getDim());
        $this->assertNull($this->map(['opacity' => 'half'])->getDim());

        $this->assertTrue($this->map(['filter' => 'invert(1)'])->getReverse());
        $this->assertTrue($this->map(['filter' => 'invert()'])->getReverse());
        $this->assertFalse($this->map(['filter' => 'none'])->getReverse());
        $this->assertFalse($this->map(['filter' => 'blur(2px)'])->getReverse(), 'blur() is accepted, without effect.');
    }

    public function testFilterFunctions()
    {
        $filter = $this->map(['filter' => 'grayscale(50%) hue-rotate(0.5turn) brightness() invert(0.25)'])->getFilter();
        $this->assertInstanceOf(Filter::class, $filter);
        $this->assertSame([['grayscale', 0.5], ['hue-rotate', 180.0], ['brightness', 1.0], ['invert', 0.25]], $filter->functions);
        $this->assertFalse($this->map(['filter' => 'invert(1)'])->getFilter(), 'A full invert() alone is reverse video.');
        $this->assertFalse($this->map(['filter' => 'none'])->getFilter());

        $this->assertNull($this->map(['filter' => 'grayscale(-1)'])->getFilter(), 'No negative amounts.');
        $this->assertSame([['sepia', 1.0]], $this->map(['filter' => 'sepia(1) blur(2px)'])->getFilter()?->functions, 'blur() is left out.');
        $this->assertNull($this->map(['filter' => 'sepia(1) url(#f)'])->getFilter(), 'One function not supported makes it all invalid.');
        $this->assertSame(2, $this->map(['filter' => 'drop-shadow(2ch 1 red) sepia(1)'])->getShadow()?->x, 'drop-shadow() is the shadow of the box.');
        $this->assertSame(3, $this->map(['filter' => 'drop-shadow(2ch 1 red)', 'box-shadow' => '3ch 1 blue'])->getShadow()?->x, 'Unless box-shadow sets one.');
        $shadow = $this->map(['box-shadow' => '1ch 1 2ch 1ch red'])->getShadow();
        $this->assertSame([1, 1, 2, 1, [3, 3]], [$shadow?->x, $shadow?->y, $shadow?->blur, $shadow?->spread, $shadow?->extents()], 'The blur and the spread; a cell more each way for the blur.');
        $this->assertSame([0, 0], $this->map(['box-shadow' => '1ch 1 0 -2ch red'])->getShadow()?->extents(), 'A negative spread hides a shadow smaller than its offset.');
        $this->assertNull($this->map(['filter' => 'hue-rotate(10px)'])->getFilter());
        $this->assertNull($this->map(['filter' => 'brightness(1, 2)'])->getFilter());
    }

    public function testFontFamily()
    {
        $this->assertSame('big', $this->map(['font-family' => '"big", monospace'])->getFont());
        $this->assertNull($this->map(['font-family' => 'monospace'])->getFont());
        $this->assertNull($this->map(['font-family' => '""'])->getFont());
        $this->assertNull($this->map(['font-family' => 'ui-sans-serif, system-ui, Inter'])->getFont(), 'Nothing after a generic family is used.');
        $this->assertSame('Inter, slant, monospace', $this->map(['font-family' => 'Inter, "slant", sans-serif'])->getFont(), 'A list, for the first registered font.');
        $this->assertSame('Inter, monospace', $this->map(['font-family' => 'Inter'])->getFont(), 'Passed over when it is not registered.');
    }

    public function testDisplayAndVisibility()
    {
        $this->assertTrue($this->map(['display' => 'none'])->getHidden());
        $this->assertFalse($this->map(['display' => 'block'])->getHidden());
        $this->assertTrue($this->map(['visibility' => 'hidden'])->getInvisible(), 'Hidden keeps the room.');
        $this->assertNull($this->map(['visibility' => 'hidden'])->getHidden());
        $this->assertTrue($this->map(['visibility' => 'collapse'])->getHidden(), 'Collapse frees it.');
        $this->assertFalse($this->map(['visibility' => 'visible'])->getInvisible());
        $this->assertSame(TableRole::Table, $this->map(['display' => 'table'])->getTableRole());
        $this->assertSame(TableRole::RowGroup, $this->map(['display' => 'table-header-group'])->getTableRole());
        $this->assertSame(Direction::Horizontal, $this->map(['display' => 'table-row'])->getDirection());
        $this->assertSame(TableRole::Cell, $this->map(['display' => 'table-cell'])->getTableRole());
        $this->assertNull($this->map(['display' => 'table-caption'])->getTableRole(), 'Not supported.');
    }

    public function testFlexbox()
    {
        $this->assertSame(Direction::Horizontal, $this->map(['display' => 'flex'])->getDirection(), 'Flex lays out a row by default.');
        $this->assertSame(Direction::Vertical, $this->map(['display' => 'flex', 'flex-direction' => 'column'])->getDirection());
        $this->assertSame(Direction::Horizontal, $this->map(['flex-direction' => 'row-reverse'])->getDirection());
        $this->assertNull($this->map(['display' => 'block'])->getDirection());
        $this->assertNull($this->map(['flex-direction' => 'diagonal'])->getDirection());

        $this->assertSame(2, $this->map(['flex' => '2 1 0'])->getFlex());
        $this->assertSame(0, $this->map(['flex' => 'none'])->getFlex());
        $this->assertSame(1, $this->map(['flex' => 'auto'])->getFlex());
        $this->assertSame(2.6, $this->map(['flex-grow' => '2.6'])->getFlex(), 'Fractions are kept.');
        $this->assertSame(2, $this->map(['flex-grow' => '2.0'])->getFlex(), 'A whole number is an int.');
        $this->assertSame(0.5, $this->map(['flex' => '0.5 0.25'])->getFlex());
        $this->assertSame(0.25, $this->map(['flex' => '0.5 0.25'])->getShrink());
        $this->assertNull($this->map(['flex-grow' => '-1'])->getFlex(), 'Negative factors are invalid.');
        $this->assertNull($this->map(['flex' => 'big'])->getFlex());

        $this->assertSame(0, $this->map(['flex' => '2 1 0'])->getFlexBasis());
        $this->assertSame(0, $this->map(['flex' => '1'])->getFlexBasis(), 'A grow factor alone sets the basis to 0.');
        $this->assertNull($this->map(['flex' => 'auto'])->getFlexBasis());
        $this->assertSame(10, $this->map(['flex' => '10ch'])->getFlexBasis());
        $this->assertSame(1, $this->map(['flex' => '10ch'])->getFlex(), 'A basis alone grows.');
        $this->assertSame(12, $this->map(['flex' => '0 0 12ch'])->getFlexBasis());
        $this->assertSame(0, $this->map(['flex' => '0 0 12ch'])->getShrink());
        $this->assertSame(8, $this->map(['flex-basis' => '8'])->getFlexBasis());
        $this->assertNull($this->map(['flex-basis' => 'content'])->getFlexBasis());
        $this->assertNull($this->map(['flex-basis' => '30%'])->getFlexBasis());
        $this->assertSame(30, $this->map(['flex-basis' => '30%'])->getFlexBasisPercent());
        $this->assertSame(18, $this->map(['flex' => '1 1 30%'])->resolveFlexBasis(60));
        $this->assertNull($this->map(['flex-basis' => '130%'])->getFlexBasisPercent());
        $this->assertNull($this->map(['flex-basis' => '30%', 'flex' => '1 1 4ch'])->getFlexBasisPercent(), 'A basis in columns replaces the percentage.');
        $this->assertNull($this->map(['flex' => '1 2 3 4'])->getFlex());
    }

    public function testGapFollowsTheDirection()
    {
        $this->assertSame(2, $this->map(['gap' => '2'])->getGap());
        $this->assertSame(1, $this->map(['gap' => '16px'])->getGap(), 'A column gap is in rows.');
        $this->assertSame(2, $this->map(['display' => 'flex', 'gap' => '16px'])->getGap(), 'A row gap is in columns.');
        $this->assertSame(0, $this->map(['gap' => '.25rem'])->getGap(), 'A row from three quarters of one: a quarter is none.');
        $this->assertSame(1, $this->map(['display' => 'flex', 'gap' => '.25rem'])->getGap(), 'Between columns, any gap is one.');
        $this->assertSame([0, 1], [$this->map(['position' => 'absolute', 'top' => '.5rem', 'left' => '.5rem'])->getPosition()?->top, $this->map(['position' => 'absolute', 'top' => '.5rem', 'left' => '.5rem'])->getPosition()?->left], 'Insets in whole cells: .5rem is half a row, none, and a whole column.');
        $this->assertSame(3, $this->map(['display' => 'flex', 'gap' => '1 3'])->getGap(), 'A row uses the column gap.');
        $this->assertSame(1, $this->map(['gap' => '1 3'])->getGap(), 'A column uses the row gap.');
        $this->assertSame(4, $this->map(['display' => 'flex', 'gap' => '1', 'column-gap' => '4'])->getGap());
        $this->assertSame(5, $this->map(['row-gap' => '5', 'column-gap' => '4'])->getGap());
        $this->assertSame(0, $this->map(['gap' => 'normal'])->getGap());
        $this->assertNull($this->map(['gap' => 'wide'])->getGap());
        $this->assertTrue($this->logger->has('Invalid value "wide" for the "gap" CSS property.'));
    }

    public function testAlignmentFollowsTheDirection()
    {
        $row = $this->map(['display' => 'flex', 'justify-content' => 'center', 'align-items' => 'flex-end']);
        $this->assertSame(Align::Center, $row->getAlign());
        $this->assertSame(VerticalAlign::Bottom, $row->getVerticalAlign());

        $column = $this->map(['justify-content' => 'end', 'align-items' => 'center']);
        $this->assertSame(VerticalAlign::Bottom, $column->getVerticalAlign());
        $this->assertSame(Align::Center, $column->getAlign());

        $this->assertSame(Align::Left, $this->map(['align-items' => 'start'])->getAlign());
        $this->assertSame(VerticalAlign::Top, $this->map(['justify-content' => 'flex-start'])->getVerticalAlign());
        $this->assertNull($this->map(['align-items' => 'stretch'])->getAlign());
    }

    public function testWidth()
    {
        $this->assertSame(40, $this->map(['max-width' => '40ch'])->getMaxColumns());
        $this->assertSame(10, $this->map(['width' => '80px'])->getMaxColumns());
        $this->assertSame(1, $this->map(['max-width' => '0'])->getMaxColumns());
        $this->assertNull($this->map(['max-width' => '20', 'width' => 'none'])->getMaxColumns());
        $this->assertNull($this->map(['max-width' => '50%'])->getMaxColumns());
    }

    public function testTranslucentColorsAreBlended()
    {
        $this->assertSame('#800000', $this->map(['color' => 'rgba(255, 0, 0, .5)'])->getColor()?->toHex(), 'Over the dark terminal background.');
        $this->assertSame('#ff8080', $this->mapper->map(['color' => 'rgb(255 0 0 / 50%)'], new MediaContext(colorScheme: ColorScheme::Light))->getColor()?->toHex());
        $this->assertSame('#800080', $this->map(['color' => '#ff000080', 'background-color' => '#0000ff'])->getColor()?->toHex(), 'Text over its own background.');
        $this->assertSame('#4000ff', $this->mapper->map(['background' => '#ff00ff40'], new MediaContext(), Color::hex('#0000ff'))->getBackground()?->toHex(), 'A background over the one behind.');
        $this->assertSame('#ff0000', $this->map(['color' => '#f00f'])->getColor()?->toHex(), '#rgba');
        $this->assertNull($this->map(['background' => 'hsla(0, 100%, 50%, 0)'])->getBackground(), 'Fully transparent.');
        $this->assertSame(0.25, Value::alpha('rgba(0,0,0,25%)'));
        $this->assertSame(1.0, Value::alpha('red'));
    }

    public function testRightToLeft()
    {
        $rtl = $this->map(['direction' => 'rtl', 'padding-inline-start' => '2', 'margin-inline' => '1 3']);
        $this->assertSame([0, 2, 0, 0], [$rtl->getPadding()?->top, $rtl->getPadding()?->right, $rtl->getPadding()?->bottom, $rtl->getPadding()?->left], 'The inline start is the right.');
        $this->assertSame([1, 3], [$rtl->getMargin()?->right, $rtl->getMargin()?->left]);
        $this->assertSame(TextAlign::Right, $rtl->getTextAlign(), 'Text starts on the right.');
        $this->assertSame(TextAlign::Left, $this->map(['direction' => 'rtl', 'text-align' => 'end'])->getTextAlign());

        $row = $this->map(['direction' => 'rtl', 'display' => 'flex']);
        $this->assertTrue($row->getReverseOrder(), 'A row runs from the right.');
        $this->assertSame(Align::Right, $row->getAlign());
        $this->assertFalse($this->map(['direction' => 'rtl', 'display' => 'flex', 'flex-direction' => 'row-reverse'])->getReverseOrder());
        $this->assertSame(AutoMargin::Left, $this->map(['direction' => 'rtl', 'max-width' => '10'])->getAutoMargin(), 'A narrower block starts on the right.');

        $ltr = $this->map(['padding-inline-start' => '2', 'position' => 'absolute', 'inset-inline-start' => '3', 'inset-block' => '1 2']);
        $this->assertSame(2, $ltr->getPadding()?->left);
        $this->assertSame([1, null, 2, 3], [$ltr->getPosition()?->top, $ltr->getPosition()?->right, $ltr->getPosition()?->bottom, $ltr->getPosition()?->left]);
    }

    public function testTranslationsAndOverline()
    {
        $moved = $this->map(['transform' => 'translate(2ch, 1)'])->getPosition();
        $this->assertSame([PositionMode::Relative, 1, 2], [$moved?->mode, $moved?->top, $moved?->left]);
        $absolute = $this->map(['position' => 'absolute', 'right' => '2', 'transform' => 'translateX(1)'])->getPosition();
        $this->assertSame([null, 1], [$absolute?->top, $absolute?->right], 'Only the inset along the axis moves.');
        $this->assertNull($this->map(['transform' => 'rotate(5deg)'])->getPosition(), 'Only translations.');
        $this->assertTrue($this->mapper->supports('transform', 'scale(1.05) rotate(45deg) skewX(10deg) matrix(1, 0, 0, 1, 0, 0) perspective(100px)'), 'Accepted, changing nothing: cells are neither scaled nor turned.');
        $this->assertFalse($this->mapper->supports('transform', 'bogus(1)'));
        $this->assertSame([2, 1], [$this->map(['position' => 'relative', 'transform' => 'scale(2) translate3d(2ch, 1, 0)'])->getPosition()?->left, $this->map(['position' => 'relative', 'transform' => 'scale(2) translate3d(2ch, 1, 0)'])->getPosition()?->top], 'The x and y of translate3d().');
        $this->assertTrue($this->mapper->supports('transform', 'translate(-50%, -50%)'));
        $hidden = $this->map(['position' => 'fixed', 'left' => '0', 'transform' => 'translateX(calc(0px - min(10ch, 80vw) - 1ch))'])->getPosition();
        $this->assertSame(-11, $hidden?->left, 'The comma of a function in the length is not one of the translation, like the sidebar of mdBook moved out of view.');
        $centered = $this->map(['position' => 'absolute', 'left' => '50%', 'transform' => 'translate(-50%, 1)'])->getPosition();
        $this->assertSame([[-50.0, 0.0], 1], [$centered?->translate, $centered?->top], 'Percentages are of the element itself: kept for when its size is known.');
        $this->assertSame([-5, 0], $centered?->translation(10, 3), 'Half of its width to the left; its 1 row down is the top inset.');

        $position = $this->map(['position' => 'absolute', 'inset' => 'max(1, 10%) 5% auto', 'left' => 'calc(50% - 2ch)'])->getPosition();
        $this->assertSame([3, 2, 18, null], [$position?->inset('top', 30), $position?->inset('right', 40), $position?->inset('left', 40), $position?->inset('bottom', 40)], 'Percentages of the containing block in calc(), min(), max() and clamp().');
        $this->assertSame(1, $position?->inset('top', 4));

        $this->assertTrue($this->map(['text-decoration' => 'overline'])->getOverline());
        $this->assertSame([true, true], [$this->map(['text-decoration-line' => 'underline overline'])->getUnderline(), $this->map(['text-decoration-line' => 'underline overline'])->getOverline()]);
        $this->assertSame("\e[53mx\e[55m", (new Style(overline: true))->apply('x'));
    }

    public function testTextAlign()
    {
        $this->assertSame(TextAlign::Justify, $this->map(['text-align' => 'justify'])->getTextAlign());
        $this->assertSame(TextAlign::Center, $this->map(['text-align' => 'center'])->getTextAlign());
        $this->assertSame(TextAlign::Right, $this->map(['text-align' => 'end'])->getTextAlign());
        $this->assertNull($this->map(['text-align' => 'middle'])->getTextAlign());
    }

    public function testCaretShape()
    {
        $this->assertSame(CursorShape::Bar, $this->map(['caret-shape' => 'auto'])->getCursorShape());
        $this->assertSame(CursorShape::Block, $this->map(['caret-shape' => 'block'])->getCursorShape());
        $this->assertSame(CursorShape::Underline, $this->map(['caret-shape' => 'underscore'])->getCursorShape());
        $this->assertNull($this->map(['caret-shape' => 'heart'])->getCursorShape());

        // caret-animation: manual, a caret that does not blink; the caret shorthand
        $this->assertSame(CursorShape::SteadyUnderline, $this->map(['caret-shape' => 'underscore', 'caret-animation' => 'manual'])->getCursorShape());
        $this->assertSame(CursorShape::SteadyBlock, $this->map(['caret-animation' => 'manual'])->getCursorShape(), 'A block, like the caret of fields, by default.');
        $this->assertSame(CursorShape::SteadyBar, $this->map(['caret' => 'red manual bar'])->getCursorShape());
        $this->assertSame(CursorShape::Bar, $this->map(['caret' => 'manual bar', 'caret-animation' => 'auto'])->getCursorShape(), 'The longhand after the shorthand wins.');
        $this->assertSame(CursorShape::Block, $this->map(['caret' => 'block'])->getCursorShape());
        $this->assertSame(CursorShape::SteadyBar, CursorShape::SteadyBar->steady());
    }

    public function testFontSize()
    {
        $scale = fn (string $size): ?int => $this->map(['font-size' => $size])->getFontScale();
        $this->assertSame([2, 2, 3, 2, 4, 2], array_map($scale, ['2em', '32px', '48px', '200%', '10rem', 'xx-large']));
        // Once: none
        $this->assertSame([null, null, null, null], array_map($scale, ['1em', 'medium', 'smaller', 'clamp(1rem, 2vw, 3rem)']));
        $this->assertNull($scale('12'), 'A number alone is invalid.');
        $this->assertTrue($this->mapper->supports('font-size', '2em'));
        $this->assertFalse($this->mapper->supports('font-size', 'big'));
    }

    public function testVerticalWritingModes()
    {
        $style = $this->map(['writing-mode' => 'vertical-rl', 'padding-inline-start' => '1', 'padding-block' => '2 3', 'inline-size' => '4']);
        $this->assertSame('vertical-rl', $style->getWritingMode());
        // The inline start is the top, the block start the right, the inline size the height
        $this->assertSame([1, 2, 0, 3], [$style->getPadding()->top, $style->getPadding()->right, $style->getPadding()->bottom ?? 0, $style->getPadding()->left]);
        $this->assertSame(4, $this->map(['writing-mode' => 'vertical-rl', 'inline-size' => '4'])->getMaxRows());
        $this->assertSame(10, $this->map(['writing-mode' => 'vertical-rl', 'block-size' => '10ch'])->getMaxColumns());

        $style = $this->map(['writing-mode' => 'vertical-lr', 'margin-block-start' => '2']);
        $this->assertSame(2, $style->getMargin()->left, 'Blocks from the left.');
        $this->assertSame(3, $this->map(['writing-mode' => 'vertical-rl', 'direction' => 'rtl', 'padding-inline-start' => '3'])->getPadding()->bottom, 'Right to left, the inline start is the bottom.');

        $this->assertNull($this->map(['writing-mode' => 'horizontal-tb'])->getWritingMode());
        $this->assertSame('vertical-rl', $this->map(['writing-mode' => 'tb-rl'])->getWritingMode(), 'The SVG 1.1 values.');
        $this->assertNull($this->map(['writing-mode' => 'diagonal'])->getWritingMode());
    }

    public function testVerticalGrid()
    {
        // Laid out as a horizontal grid: its columns are the rows of that grid, its rows its columns, from the right
        $style = $this->map(['display' => 'grid', 'writing-mode' => 'vertical-rl', 'grid-template-columns' => '3 4', 'grid-template-rows' => '5ch 1fr', 'gap' => '1 2']);
        $grid = $style->getGridTemplate();
        $this->assertSame([['cells', 5.0, 0], ['fr', 1.0, 0]], $grid->tracks);
        $this->assertSame([3, 4], $grid->rows);
        $this->assertTrue($grid->columnFlow, 'Its items fill its columns first.');
        $this->assertTrue($style->getReverseOrder(), 'Its first row on the right.');
        $this->assertSame([1, 2], [$style->getGap(), $style->getRowGap()], 'The gap between its rows is across.');
        $this->assertSame('fit-content', $style->getContentWidth(), 'As wide as its rows.');

        $style = $this->map(['display' => 'grid', 'writing-mode' => 'vertical-lr', 'direction' => 'rtl', 'grid-auto-flow' => 'column dense', 'width' => '20', 'grid-template-rows' => 'repeat(auto-fill, 4)']);
        $this->assertFalse($style->getReverseOrder(), 'From the left, whatever its direction.');
        $this->assertFalse($style->getGridTemplate()->columnFlow, 'Its rows filled first.');
        $this->assertTrue($style->getGridTemplate()->dense);
        $this->assertNotNull($style->getGridTemplate()->autoRepeat, 'Its rows fill its width when set.');
        $this->assertNull($this->map(['display' => 'grid', 'writing-mode' => 'vertical-lr', 'grid-template-rows' => 'repeat(auto-fill, 4)'])->getGridTemplate()->autoRepeat, 'Once without a width.');

        $grid = $this->map(['display' => 'grid', 'writing-mode' => 'vertical-rl', 'grid-template-areas' => '"a b" "c c"'])->getGridTemplate();
        $this->assertSame(['a' => [0, 0, 0, 0], 'c' => [0, 1, 1, 1], 'b' => [1, 0, 1, 0]], $grid->areas, 'Its areas transposed.');

        // Its items placed with their rows and columns the other way around
        $placement = $this->mapper->map(['grid-row' => '2', 'grid-column' => '1 / span 3'], new MediaContext(), null, true)->getGridPlacement();
        $this->assertSame([1, 3, 2], [$placement->rowStart, $placement->rowSpan, $placement->columnStart]);
        $placement = $this->mapper->map(['grid-area' => '1 / 2 / 3'], new MediaContext(), null, true)->getGridPlacement();
        $this->assertSame([2, 1, 3], [$placement->rowStart, $placement->columnStart, $placement->columnEnd]);
        $this->assertSame('main', $this->mapper->map(['grid-area' => 'main'], new MediaContext(), null, true)->getGridPlacement()->area);
        $this->assertNull($this->map(['display' => 'grid', 'grid-row' => '2'])->getGridPlacement()->columnStart, 'Not in a grid written vertically.');
    }

    public function testCaretColor()
    {
        $this->assertSame('#cd0000', $this->map(['caret-color' => 'red'])->getCaretColor()?->toHex(), 'The ANSI red, in its xterm shade.');
        $this->assertSame('#102030', $this->map(['caret-color' => '#102030'])->getCaretColor()?->toHex());
        $this->assertNull($this->map(['caret-color' => 'auto'])->getCaretColor(), 'The color of the terminal cursor.');
        $this->assertNull($this->map(['caret-color' => 'transparent'])->getCaretColor());
        $this->assertNull($this->map(['caret-color' => 'nope'])->getCaretColor());
        $this->assertSame('#00ff00', $this->map(['caret' => 'lime manual bar'])->getCaretColor()?->toHex(), 'The caret shorthand.');
        $this->assertNull($this->map(['caret' => 'auto'])->getCaretColor());
    }

    public function testKeywordsAndCustomPropertiesAreSkipped()
    {
        $style = $this->map(['--accent' => 'red', 'color' => 'inherit', 'font-weight' => 'initial', 'text-shadow' => '1px red', 'transition' => 'all 1s']);

        $this->assertNull($style->getColor());
        $this->assertFalse($style->getBold(), 'An initial text look is set: not bold, over a heading class.');
        $this->assertSame([], $this->logger->messages, 'Ignored properties are not reported.');
        $this->assertSame(Color::named('default')->toForegroundCode(), $this->map(['color' => 'initial'])->getColor()?->toForegroundCode(), 'color: initial is the terminal one.');
    }

    public function testUnknownPropertyIsReportedOnce()
    {
        $this->map(['tui-nonsense' => 'circle()']);
        $this->map(['tui-nonsense' => 'none']);

        $this->assertSame(['The "tui-nonsense" CSS property is not supported.'], $this->logger->messages);
    }

    public function testStrict()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "tui-nonsense" CSS property is not supported.');

        (new DeclarationMapper(strict: true))->map(['tui-nonsense' => 'circle()'], new MediaContext());
    }

    public function testStrictInvalidValue()
    {
        $this->expectException(InvalidArgumentException::class);

        (new DeclarationMapper(strict: true))->map(['color' => 'nope'], new MediaContext());
    }

    public function testAddProperty()
    {
        $this->mapper->addProperty('Tui-Reverse', static fn (Style $s, string $v) => 'on' === $v ? $s->withReverse() : false);

        $this->assertTrue($this->map(['tui-reverse' => 'on'])->getReverse());
        $this->assertNull($this->map(['tui-reverse' => 'maybe'])->getReverse());
        $this->assertSame(SupportLevel::Supported, $this->mapper->getSupport()['css.properties.tui-reverse']->level);
    }

    public function testSupport()
    {
        $support = $this->mapper->getSupport();

        $this->assertSame(SupportLevel::Supported, $support['css.properties.color']->level);
        $this->assertSame(SupportLevel::Partial, $support['css.properties.border-radius']->level);
        $this->assertSame(SupportLevel::Ignored, $support['css.properties.zoom']->level);
        $this->assertArrayHasKey('css.types.color.light-dark', $support);
    }

    /**
     * @param array<string, string> $declarations
     */
    private function map(array $declarations, ?MediaContext $media = null): Style
    {
        return $this->mapper->map($declarations, $media ?? new MediaContext());
    }

    /**
     * @return list<int>|null
     */
    private static function padding(Style $style): ?array
    {
        $padding = $style->getPadding();

        return null === $padding ? null : [$padding->top, $padding->right, $padding->bottom, $padding->left];
    }
}
