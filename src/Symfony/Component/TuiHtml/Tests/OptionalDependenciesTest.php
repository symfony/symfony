<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\CollectingLogger;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Tests\Fixtures\MissingPackages;
use Symfony\Component\TuiHtml\Widget\TextWidget;

/**
 * TuiHtml renders HTML with none of the packages it suggests installed.
 */
final class OptionalDependenciesTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testMarkdownIsDrawnAsWrittenWithoutCommonMark()
    {
        MissingPackages::hide('League\\CommonMark\\');
        $this->assertFalse(class_exists('League\\CommonMark\\Parser\\MarkdownParser'));
        $logger = new CollectingLogger();

        $view = (new HtmlBuilder(new CssStyleSheet(), null, $logger))->build("<p>Intro</p><tui-markdown>\n  # Title\n  *text*\n</tui-markdown><tui-markdown>more</tui-markdown>");
        $markdown = $view->getBody()->all()[1];

        $this->assertInstanceOf(TextWidget::class, $markdown);
        $this->assertSame("# Title\n*text*", $markdown->getText());
        $this->assertSame(['<tui-markdown> is drawn as written: install league/commonmark and tempest/highlight to render Markdown.'], $logger->messages, 'Once for the document.');
        $output = $this->draw($view);
        $this->assertStringContainsString('# Title', $output);
        $this->assertStringContainsString('Intro', $output);
    }

    #[RunInSeparateProcess]
    public function testHtmlIsDrawnWithoutAnySuggestedPackage()
    {
        MissingPackages::hide('League\\', 'Tempest\\', 'Twig\\', 'Symfony\\UX\\', 'Symfony\\Component\\AssetMapper\\', 'Org\\Heigl\\', 'Vanderlee\\');

        $view = (new HtmlBuilder())->build('<style>p { hyphens: auto }</style><h1>Title</h1><p lang="en">An extraordinarily long paragraph</p><input aria-label="Name"><button>Save</button>');
        $output = $this->draw($view);

        foreach (['Title', 'paragraph', 'Save'] as $text) {
            $this->assertStringContainsString($text, $output);
        }
    }

    private function draw(\Symfony\Component\TuiHtml\Html\HtmlView $view): string
    {
        $terminal = new VirtualTerminal(30, 12);
        $tui = new Tui(null, $terminal);
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $tui->stop();

        return preg_replace('/\e\[[0-9;?]*[A-Za-z]|\e\][^\a]*\a/', '', $terminal->getOutput());
    }
}
