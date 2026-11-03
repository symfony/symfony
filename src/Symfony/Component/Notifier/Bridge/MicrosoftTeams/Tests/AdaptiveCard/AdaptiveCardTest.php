<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests\AdaptiveCard;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Action\OpenUrl;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Action\ShowCard;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Action\ToggleVisibility;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\ActionStyle;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\AdaptiveCard;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\BackgroundImage;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\BlockElementHeight;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Chart;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\ChartData;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\ChartDataPoint;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\ChartDisplayMode;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\ChartType;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Color;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Column;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\ColumnSet;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Container;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\ContainerStyle;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Data\Query;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Fact;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\FactSet;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\FallbackOption;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\GaugeSegment;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\GaugeValueFormat;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Image;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Input\Choice;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Input\ChoiceSet;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Input\Date;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Microsoft\CodeBlock;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Microsoft\Icon;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Microsoft\IconSize;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\RichTextBlock;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Spacing;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Table;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\TableCell;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\TableRow;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\TargetElement;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\TextBlock;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\TextRun;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Version;

final class AdaptiveCardTest extends TestCase
{
    public function testDefaults()
    {
        $this->assertSame([
            'type' => 'AdaptiveCard',
            '$schema' => 'https://adaptivecards.io/schemas/adaptive-card.json',
            'version' => '1.0',
        ], (new AdaptiveCard())->toArray());
    }

    public function testCardProperties()
    {
        $card = (new AdaptiveCard(Version::Version15))
            ->body(new TextBlock('first'))
            ->body(new TextBlock('second'))
            ->action((new OpenUrl('https://symfony.com'))->title('Open')->style(ActionStyle::Positive))
            ->backgroundImage('https://symfony.com/logo.png')
            ->fallbackText('fallback')
            ->msTeams(['width' => 'Full']);

        $this->assertSame([
            'type' => 'AdaptiveCard',
            '$schema' => 'https://adaptivecards.io/schemas/adaptive-card.json',
            'version' => '1.5',
            'body' => [
                ['type' => 'TextBlock', 'text' => 'first'],
                ['type' => 'TextBlock', 'text' => 'second'],
            ],
            'actions' => [
                ['type' => 'Action.OpenUrl', 'url' => 'https://symfony.com', 'title' => 'Open', 'style' => 'positive'],
            ],
            'backgroundImage' => 'https://symfony.com/logo.png',
            'fallbackText' => 'fallback',
            'msteams' => ['width' => 'Full'],
        ], $card->toArray());
    }

    public function testElementProperties()
    {
        $element = (new TextBlock('text'))
            ->id('id')
            ->isVisible(false)
            ->requires(['adaptiveCards' => '1.5'])
            ->fallback(FallbackOption::Drop)
            ->height(BlockElementHeight::Stretch)
            ->separator(true)
            ->spacing(Spacing::Large)
            ->color(Color::Good)
            ->wrap(true);

        $this->assertSame([
            'type' => 'TextBlock',
            'text' => 'text',
            'id' => 'id',
            'isVisible' => false,
            'requires' => ['adaptiveCards' => '1.5'],
            'fallback' => 'drop',
            'height' => 'stretch',
            'separator' => true,
            'spacing' => 'Large',
            'color' => 'good',
            'wrap' => true,
        ], $element->toArray());
    }

    public function testFallbackElement()
    {
        $element = (new Icon('Calendar'))->fallback(new TextBlock('calendar'));

        $this->assertSame(['type' => 'TextBlock', 'text' => 'calendar'], $element->toArray()['fallback']);
    }

    public function testRequiredListsAreAlwaysPresent()
    {
        $this->assertSame(['type' => 'Container', 'items' => []], (new Container())->toArray());
        $this->assertSame(['type' => 'FactSet', 'facts' => []], (new FactSet())->toArray());
        $this->assertSame(['type' => 'RichTextBlock', 'inlines' => []], (new RichTextBlock())->toArray());
        $this->assertSame(['type' => 'Action.ToggleVisibility', 'targetElements' => []], (new ToggleVisibility())->toArray());
    }

    public function testNestedElements()
    {
        $columnSet = (new ColumnSet())
            ->column((new Column())
                ->width('auto')
                ->item(new Image('https://symfony.com/logo.png'))
                ->backgroundImage(new BackgroundImage('https://symfony.com/background.png'))
            )
            ->column((new Column())
                ->width(2)
                ->item((new FactSet())->fact(new Fact('Name', 'Symfony')))
                ->style(ContainerStyle::Emphasis)
            );

        $this->assertSame([
            'type' => 'ColumnSet',
            'columns' => [
                [
                    'type' => 'Column',
                    'width' => 'auto',
                    'items' => [['type' => 'Image', 'url' => 'https://symfony.com/logo.png']],
                    'backgroundImage' => ['type' => 'BackgroundImage', 'url' => 'https://symfony.com/background.png'],
                ],
                [
                    'type' => 'Column',
                    'width' => 2,
                    'items' => [['type' => 'FactSet', 'facts' => [['title' => 'Name', 'value' => 'Symfony']]]],
                    'style' => 'emphasis',
                ],
            ],
        ], $columnSet->toArray());
    }

    public function testImageHeightAcceptsPixels()
    {
        $this->assertSame('50px', (new Image('https://symfony.com/logo.png'))->height('50px')->toArray()['height']);
        $this->assertSame('auto', (new Image('https://symfony.com/logo.png'))->height(BlockElementHeight::Auto)->toArray()['height']);
    }

    public function testRichTextBlock()
    {
        $block = (new RichTextBlock())
            ->inline('plain')
            ->inline((new TextRun('bold'))->italic(true));

        $this->assertSame(['plain', ['type' => 'TextRun', 'text' => 'bold', 'italic' => true]], $block->toArray()['inlines']);
    }

    public function testTable()
    {
        $table = (new Table())
            ->firstRowAsHeader(true)
            ->row((new TableRow())->cell((new TableCell())->item(new TextBlock('cell'))));

        $this->assertSame([
            'type' => 'Table',
            'firstRowAsHeader' => true,
            'rows' => [
                ['type' => 'TableRow', 'cells' => [['type' => 'TableCell', 'items' => [['type' => 'TextBlock', 'text' => 'cell']]]]],
            ],
        ], $table->toArray());
    }

    public function testChart()
    {
        $chart = (new Chart(ChartType::HorizontalBarStacked))
            ->showLegend(false)
            ->data((new ChartData())
                ->title('Results')
                ->data((new ChartDataPoint())->legend('Success')->value(3)->color('good'))
            );

        $this->assertSame([
            'type' => 'Chart.HorizontalBar.Stacked',
            'showLegend' => false,
            'data' => [
                ['title' => 'Results', 'data' => [['legend' => 'Success', 'value' => 3, 'color' => 'good']]],
            ],
        ], $chart->toArray());
    }

    public function testBarChart()
    {
        $chart = (new Chart(ChartType::HorizontalBar))
            ->displayMode(ChartDisplayMode::PartToWhole)
            ->xAxisTitle('Fruit')
            ->yAxisTitle('Sales')
            ->data((new ChartDataPoint())->x('Pear')->y(59)->color('good'))
            ->data((new ChartDataPoint())->x('Banana')->y(292));

        $this->assertSame([
            'type' => 'Chart.HorizontalBar',
            'displayMode' => 'PartToWhole',
            'xAxisTitle' => 'Fruit',
            'yAxisTitle' => 'Sales',
            'data' => [
                ['x' => 'Pear', 'y' => 59, 'color' => 'good'],
                ['x' => 'Banana', 'y' => 292],
            ],
        ], $chart->toArray());
    }

    public function testSeriesChart()
    {
        $chart = (new Chart(ChartType::VerticalBarGrouped))
            ->stacked(true)
            ->showBarValues(true)
            ->colorSet('diverging')
            ->data((new ChartData())
                ->legend('Outlook')
                ->color('categoricalBlue')
                ->value((new ChartDataPoint())->x('2023-05-01')->y(24))
                ->value((new ChartDataPoint())->x('2023-05-02')->y(48))
            );

        $this->assertSame([
            'type' => 'Chart.VerticalBar.Grouped',
            'stacked' => true,
            'showBarValues' => true,
            'colorSet' => 'diverging',
            'data' => [
                [
                    'legend' => 'Outlook',
                    'color' => 'categoricalBlue',
                    'values' => [['x' => '2023-05-01', 'y' => 24], ['x' => '2023-05-02', 'y' => 48]],
                ],
            ],
        ], $chart->toArray());
    }

    public function testGaugeChart()
    {
        $chart = (new Chart(ChartType::Gauge))
            ->value(35)
            ->min(0)
            ->max(100)
            ->subLabel('Used')
            ->showMinMax(false)
            ->valueFormat(GaugeValueFormat::Fraction)
            ->segment((new GaugeSegment(35))->legend('Used')->color('good'))
            ->segment((new GaugeSegment(65))->legend('Unused')->color('neutral'));

        $this->assertSame([
            'type' => 'Chart.Gauge',
            'value' => 35,
            'min' => 0,
            'max' => 100,
            'subLabel' => 'Used',
            'showMinMax' => false,
            'valueFormat' => 'Fraction',
            'segments' => [
                ['size' => 35, 'legend' => 'Used', 'color' => 'good'],
                ['size' => 65, 'legend' => 'Unused', 'color' => 'neutral'],
            ],
        ], $chart->toArray());
    }

    public function testInputs()
    {
        $date = (new Date('date'))
            ->label('Date')
            ->isRequired(true)
            ->min(new \DateTimeImmutable('2026-01-02 10:00'));

        $this->assertSame(['type' => 'Input.Date', 'id' => 'date', 'label' => 'Date', 'isRequired' => true, 'min' => '2026-01-02'], $date->toArray());

        $choiceSet = (new ChoiceSet('choice'))
            ->choice(new Choice('One', '1'))
            ->choicesData((new Query('dataset'))->count(5));

        $this->assertSame([
            'type' => 'Input.ChoiceSet',
            'id' => 'choice',
            'choices' => [['type' => 'Input.Choice', 'title' => 'One', 'value' => '1']],
            'choices.data' => ['type' => 'Data.Query', 'dataset' => 'dataset', 'count' => 5],
        ], $choiceSet->toArray());
    }

    public function testActions()
    {
        $toggle = (new ToggleVisibility())
            ->targetElement('details')
            ->targetElement((new TargetElement('summary'))->isVisible(false));

        $this->assertSame(['details', ['type' => 'TargetElement', 'elementId' => 'summary', 'isVisible' => false]], $toggle->toArray()['targetElements']);

        $showCard = (new ShowCard())
            ->fallback(FallbackOption::Drop)
            ->card((new AdaptiveCard())->body(new TextBlock('details')));

        $this->assertSame('drop', $showCard->toArray()['fallback']);
        $this->assertSame([['type' => 'TextBlock', 'text' => 'details']], $showCard->toArray()['card']['body']);
    }

    public function testMicrosoftElements()
    {
        $this->assertSame(['type' => 'Icon', 'name' => 'Calendar', 'size' => 'xSmall'], (new Icon('Calendar'))->size(IconSize::XSmall)->toArray());
        $this->assertSame(['type' => 'CodeBlock', 'codeSnippet' => 'echo 1;'], (new CodeBlock('echo 1;'))->toArray());
    }
}
